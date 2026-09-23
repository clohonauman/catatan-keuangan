import Foundation
import Combine
import WebKit

@MainActor
final class AppModel: ObservableObject {
    let network = NetworkMonitor()
    let biometrics = BiometricManager()

    @Published var showSplash = true
    @Published var pageProgress: Double = 0
    @Published var pageLoading = true

    weak var webView: WKWebView?
    private var backgroundedAt: Date?

    func attach(webView: WKWebView) {
        self.webView = webView
    }

    func finishSplashAndUnlockIfNeeded() {
        showSplash = false
        if biometrics.isEnabled {
            biometrics.requestUnlock()
        }
    }

    func sceneDidEnterBackground() {
        backgroundedAt = Date()
    }

    func sceneDidBecomeActive() {
        defer { backgroundedAt = nil }
        guard biometrics.isEnabled,
              let backgroundedAt,
              Date().timeIntervalSince(backgroundedAt) >= AppConfig.biometricRelockInterval else {
            return
        }
        biometrics.lock()
        biometrics.requestUnlock()
    }

    func useApplicationPin() {
        biometrics.useApplicationPinFallback()
        var components = URLComponents(url: AppConfig.appURL, resolvingAgainstBaseURL: false)
        components?.queryItems = [URLQueryItem(name: "app_lock", value: "1")]
        webView?.load(URLRequest(url: components?.url ?? AppConfig.appURL))
    }

    func retryMainPage() {
        webView?.load(URLRequest(url: AppConfig.appURL, cachePolicy: .reloadRevalidatingCacheData))
    }

    func notifyWebOnline() {
        webView?.evaluateJavaScript("window.dispatchEvent(new Event('online'));", completionHandler: nil)
    }
}
