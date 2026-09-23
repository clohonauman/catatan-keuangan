import Foundation

enum AppConfig {
    static let appURL = URL(string: "https://catatan-keuangan.cloud/")!
    static let appHost = "catatan-keuangan.cloud"
    static let appName = "Catatan Keuangan"
    static let userAgentSuffix = "CatatanKeuanganIOS/1.0"
    static let biometricRelockInterval: TimeInterval = 30
}
