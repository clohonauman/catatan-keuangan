import Foundation
import Combine
import LocalAuthentication

extension Notification.Name {
    static let financeBiometricStatusChanged = Notification.Name("financeBiometricStatusChanged")
}

@MainActor
final class BiometricManager: ObservableObject {
    private enum Keys {
        static let enabled = "catatan_keuangan_biometric_enabled"
    }

    @Published var isLocked: Bool
    @Published var message: String = "Verifikasi biometrik untuk membuka aplikasi."
    @Published var promptRunning: Bool = false

    private let defaults = UserDefaults.standard

    var isEnabled: Bool {
        defaults.bool(forKey: Keys.enabled)
    }

    init() {
        isLocked = UserDefaults.standard.bool(forKey: Keys.enabled)
    }

    func lock(message: String = "Verifikasi biometrik untuk kembali ke aplikasi.") {
        guard isEnabled else { return }
        self.message = message
        isLocked = true
    }

    func useApplicationPinFallback() {
        isLocked = false
        promptRunning = false
        NotificationCenter.default.post(name: .financeBiometricStatusChanged, object: nil)
    }

    func statusObject() -> [String: Any] {
        let context = LAContext()
        var error: NSError?
        let supported = context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: &error)
        let label: String

        if supported {
            switch context.biometryType {
            case .faceID:
                label = "Face ID"
            case .touchID:
                label = "Touch ID"
            default:
                label = "Biometrik perangkat"
            }
        } else {
            label = "Biometrik perangkat"
        }

        return [
            "supported": supported,
            "enabled": isEnabled,
            "label": label,
            "message": supported ? "" : unavailableMessage(error)
        ]
    }

    func statusJSON() -> String {
        let object = statusObject()
        guard JSONSerialization.isValidJSONObject(object),
              let data = try? JSONSerialization.data(withJSONObject: object),
              let string = String(data: data, encoding: .utf8) else {
            return #"{"supported":false,"enabled":false,"label":"Biometrik perangkat","message":"Status biometrik tidak tersedia."}"#
        }
        return string
    }

    func requestEnable() {
        authenticate(mode: .enable)
    }

    func requestDisable() {
        guard isEnabled else {
            notifyStatusChanged()
            return
        }
        authenticate(mode: .disable)
    }

    func requestUnlock() {
        guard isEnabled else {
            isLocked = false
            return
        }
        authenticate(mode: .unlock)
    }

    private enum Mode {
        case unlock
        case enable
        case disable
    }

    private func authenticate(mode: Mode) {
        guard !promptRunning else { return }

        let context = LAContext()
        context.localizedCancelTitle = "Batal"
        context.localizedFallbackTitle = mode == .unlock ? "Gunakan Kode Perangkat" : ""

        var capabilityError: NSError?
        let biometricAvailable = context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: &capabilityError)

        if !biometricAvailable && mode != .unlock {
            message = unavailableMessage(capabilityError)
            notifyStatusChanged()
            return
        }

        var authError: NSError?
        let policy: LAPolicy = mode == .unlock ? .deviceOwnerAuthentication : .deviceOwnerAuthenticationWithBiometrics
        guard context.canEvaluatePolicy(policy, error: &authError) else {
            message = unavailableMessage(authError)
            if mode == .unlock { isLocked = true }
            notifyStatusChanged()
            return
        }

        let reason: String
        switch mode {
        case .unlock:
            reason = "Buka Catatan Keuangan dengan Face ID, Touch ID, atau kode perangkat."
        case .enable:
            reason = "Verifikasi biometrik untuk mengaktifkan kunci aplikasi."
        case .disable:
            reason = "Verifikasi biometrik untuk menonaktifkan kunci aplikasi."
        }

        promptRunning = true
        context.evaluatePolicy(policy, localizedReason: reason) { [weak self] success, error in
            DispatchQueue.main.async {
                guard let self else { return }
                self.promptRunning = false

                if success {
                    switch mode {
                    case .unlock:
                        self.isLocked = false
                    case .enable:
                        self.defaults.set(true, forKey: Keys.enabled)
                        self.isLocked = false
                    case .disable:
                        self.defaults.set(false, forKey: Keys.enabled)
                        self.isLocked = false
                    }
                    self.notifyStatusChanged()
                    return
                }

                if mode == .unlock {
                    self.isLocked = true
                    self.message = "Aplikasi tetap terkunci. Coba lagi atau gunakan PIN aplikasi."
                } else if let error {
                    self.message = error.localizedDescription
                }
                self.notifyStatusChanged()
            }
        }
    }

    private func notifyStatusChanged() {
        NotificationCenter.default.post(name: .financeBiometricStatusChanged, object: nil)
    }

    private func unavailableMessage(_ error: NSError?) -> String {
        guard let error else { return "Biometrik perangkat belum tersedia." }
        switch error.code {
        case LAError.biometryNotEnrolled.rawValue:
            return "Belum ada Face ID atau Touch ID yang didaftarkan pada perangkat."
        case LAError.biometryNotAvailable.rawValue:
            return "Perangkat ini tidak memiliki biometrik yang dapat digunakan."
        case LAError.biometryLockout.rawValue:
            return "Biometrik terkunci sementara. Gunakan kode perangkat lalu coba kembali."
        default:
            return "Biometrik perangkat belum dapat digunakan."
        }
    }
}
