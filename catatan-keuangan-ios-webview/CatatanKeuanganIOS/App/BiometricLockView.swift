import SwiftUI

struct BiometricLockView: View {
    @ObservedObject var manager: BiometricManager
    let usePin: () -> Void

    var body: some View {
        ZStack {
            Color(red: 0.965, green: 0.976, blue: 0.995)
                .ignoresSafeArea()

            VStack(spacing: 18) {
                Image(systemName: "faceid")
                    .font(.system(size: 44, weight: .regular))
                    .foregroundStyle(Color(red: 0.09, green: 0.36, blue: 0.83))
                    .frame(width: 82, height: 82)
                    .background(.white, in: RoundedRectangle(cornerRadius: 24, style: .continuous))
                    .shadow(color: .black.opacity(0.08), radius: 18, y: 8)

                VStack(spacing: 7) {
                    Text("Catatan Keuangan Terkunci")
                        .font(.system(size: 21, weight: .bold))
                    Text(manager.message)
                        .multilineTextAlignment(.center)
                        .foregroundStyle(.secondary)
                        .font(.system(size: 14))
                }
                .padding(.horizontal, 28)

                Button {
                    manager.requestUnlock()
                } label: {
                    HStack {
                        if manager.promptRunning { ProgressView().tint(.white) }
                        Text(manager.promptRunning ? "Memverifikasi..." : "Coba Biometrik")
                            .fontWeight(.semibold)
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
                }
                .buttonStyle(.borderedProminent)
                .tint(Color(red: 0.09, green: 0.36, blue: 0.83))
                .disabled(manager.promptRunning)
                .padding(.horizontal, 34)

                Button("Gunakan PIN aplikasi", action: usePin)
                    .font(.system(size: 14, weight: .semibold))
            }
        }
    }
}
