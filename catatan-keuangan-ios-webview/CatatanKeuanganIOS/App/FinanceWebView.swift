import SwiftUI
import WebKit
import UIKit

struct FinanceWebView: UIViewRepresentable {
    @ObservedObject var model: AppModel
    let isConnected: Bool

    func makeCoordinator() -> Coordinator {
        Coordinator(model: model)
    }

    func makeUIView(context: Context) -> WKWebView {
        let controller = WKUserContentController()
        controller.add(context.coordinator, name: "biometric")

        let bridgeScript = #"""
        (function(){
          if (window.__ckNativeIOSBridgeInstalled) return;
          window.__ckNativeIOSBridgeInstalled = true;
          window.__ckBiometricStatus = '{"supported":false,"enabled":false,"label":"Biometrik perangkat","message":"Memeriksa biometrik..."}';
          window.AndroidBiometric = {
            getStatus: function(){ return window.__ckBiometricStatus; },
            requestEnable: function(){ window.webkit.messageHandlers.biometric.postMessage({action:'enable'}); },
            requestDisable: function(){ window.webkit.messageHandlers.biometric.postMessage({action:'disable'}); }
          };
          document.addEventListener('DOMContentLoaded', function(){
            document.documentElement.classList.add('catatan-keuangan-native-ios');
            var iosInstall = document.getElementById('iosInstallArea');
            var desktopInstall = document.getElementById('desktopInstallArea');
            if (iosInstall) iosInstall.hidden = true;
            if (desktopInstall) desktopInstall.hidden = true;
          });
        })();
        """#
        controller.addUserScript(WKUserScript(source: bridgeScript, injectionTime: .atDocumentStart, forMainFrameOnly: false))

        let configuration = WKWebViewConfiguration()
        configuration.userContentController = controller
        configuration.websiteDataStore = .default()
        configuration.applicationNameForUserAgent = AppConfig.userAgentSuffix
        configuration.allowsInlineMediaPlayback = true
        configuration.mediaTypesRequiringUserActionForPlayback = .all

        let preferences = WKWebpagePreferences()
        preferences.allowsContentJavaScript = true
        configuration.defaultWebpagePreferences = preferences

        let webView = WKWebView(frame: .zero, configuration: configuration)
        webView.navigationDelegate = context.coordinator
        webView.uiDelegate = context.coordinator
        webView.allowsBackForwardNavigationGestures = true
        webView.scrollView.keyboardDismissMode = .interactive
        webView.scrollView.contentInsetAdjustmentBehavior = .automatic
        webView.isOpaque = false
        webView.backgroundColor = UIColor(red: 0.965, green: 0.976, blue: 0.995, alpha: 1)
        webView.scrollView.backgroundColor = webView.backgroundColor

        context.coordinator.attach(webView: webView)
        model.attach(webView: webView)
        webView.load(URLRequest(url: AppConfig.appURL))
        return webView
    }

    func updateUIView(_ webView: WKWebView, context: Context) {
        context.coordinator.updateConnectivity(isConnected)
    }

    static func dismantleUIView(_ uiView: WKWebView, coordinator: Coordinator) {
        uiView.configuration.userContentController.removeScriptMessageHandler(forName: "biometric")
        coordinator.detach()
        uiView.navigationDelegate = nil
        uiView.uiDelegate = nil
        uiView.stopLoading()
    }

    @MainActor
    final class Coordinator: NSObject, WKNavigationDelegate, WKUIDelegate, WKScriptMessageHandler, WKDownloadDelegate {
        private weak var webView: WKWebView?
        private let model: AppModel
        private var progressObservation: NSKeyValueObservation?
        private var showingOfflinePage = false
        private var previousConnectivity = true
        private var downloadDestinations: [ObjectIdentifier: URL] = [:]
        private var biometricObserver: NSObjectProtocol?

        init(model: AppModel) {
            self.model = model
            super.init()
            biometricObserver = NotificationCenter.default.addObserver(
                forName: .financeBiometricStatusChanged,
                object: nil,
                queue: .main
            ) { [weak self] _ in
                Task { @MainActor in self?.sendBiometricStatus() }
            }
        }

        func attach(webView: WKWebView) {
            self.webView = webView
            progressObservation = webView.observe(\.estimatedProgress, options: [.new]) { [weak self] view, _ in
                Task { @MainActor in
                    self?.model.pageProgress = view.estimatedProgress
                    self?.model.pageLoading = view.estimatedProgress < 1
                }
            }
        }

        func detach() {
            progressObservation?.invalidate()
            progressObservation = nil
            if let biometricObserver { NotificationCenter.default.removeObserver(biometricObserver) }
            biometricObserver = nil
        }

        func updateConnectivity(_ connected: Bool) {
            defer { previousConnectivity = connected }
            guard connected else { return }
            if showingOfflinePage {
                showingOfflinePage = false
                webView?.load(URLRequest(url: AppConfig.appURL, cachePolicy: .reloadRevalidatingCacheData))
            } else if !previousConnectivity {
                webView?.evaluateJavaScript("window.dispatchEvent(new Event('online'));", completionHandler: nil)
            }
        }

        // MARK: Navigation

        func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
            model.pageLoading = true
        }

        func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
            model.pageLoading = false
            showingOfflinePage = false
            sendBiometricStatus()
            hideInstallPrompts()
        }

        func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
            model.pageLoading = false
            guard !model.network.isConnected else { return }
            showOfflineFallback(in: webView)
        }

        func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
            model.pageLoading = false
            guard !model.network.isConnected else { return }
            showOfflineFallback(in: webView)
        }

        func webViewWebContentProcessDidTerminate(_ webView: WKWebView) {
            webView.reload()
        }

        func webView(
            _ webView: WKWebView,
            decidePolicyFor navigationAction: WKNavigationAction,
            decisionHandler: @escaping (WKNavigationActionPolicy) -> Void
        ) {
            guard let url = navigationAction.request.url else {
                decisionHandler(.cancel)
                return
            }

            if #available(iOS 14.5, *), navigationAction.shouldPerformDownload {
                decisionHandler(.download)
                return
            }

            let scheme = (url.scheme ?? "").lowercased()
            let host = (url.host ?? "").lowercased()

            if ["about", "blob", "data"].contains(scheme) {
                decisionHandler(.allow)
                return
            }

            if (scheme == "http" || scheme == "https") && host == AppConfig.appHost.lowercased() {
                decisionHandler(.allow)
                return
            }

            decisionHandler(.cancel)
            if UIApplication.shared.canOpenURL(url) {
                UIApplication.shared.open(url)
            }
        }

        func webView(
            _ webView: WKWebView,
            decidePolicyFor navigationResponse: WKNavigationResponse,
            decisionHandler: @escaping (WKNavigationResponsePolicy) -> Void
        ) {
            if #available(iOS 14.5, *), let http = navigationResponse.response as? HTTPURLResponse {
                let disposition = http.value(forHTTPHeaderField: "Content-Disposition")?.lowercased() ?? ""
                if disposition.contains("attachment") || !navigationResponse.canShowMIMEType {
                    decisionHandler(.download)
                    return
                }
            }
            decisionHandler(.allow)
        }

        // MARK: JavaScript dialogs

        func webView(_ webView: WKWebView, runJavaScriptAlertPanelWithMessage message: String, initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping () -> Void) {
            guard let presenter = UIApplication.shared.financeTopViewController else {
                completionHandler()
                return
            }
            let alert = UIAlertController(title: AppConfig.appName, message: message, preferredStyle: .alert)
            alert.addAction(UIAlertAction(title: "OK", style: .default) { _ in completionHandler() })
            presenter.present(alert, animated: true)
        }

        func webView(_ webView: WKWebView, runJavaScriptConfirmPanelWithMessage message: String, initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping (Bool) -> Void) {
            guard let presenter = UIApplication.shared.financeTopViewController else {
                completionHandler(false)
                return
            }
            let alert = UIAlertController(title: AppConfig.appName, message: message, preferredStyle: .alert)
            alert.addAction(UIAlertAction(title: "Batal", style: .cancel) { _ in completionHandler(false) })
            alert.addAction(UIAlertAction(title: "OK", style: .default) { _ in completionHandler(true) })
            presenter.present(alert, animated: true)
        }

        func webView(_ webView: WKWebView, runJavaScriptTextInputPanelWithPrompt prompt: String, defaultText: String?, initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping (String?) -> Void) {
            guard let presenter = UIApplication.shared.financeTopViewController else {
                completionHandler(nil)
                return
            }
            let alert = UIAlertController(title: AppConfig.appName, message: prompt, preferredStyle: .alert)
            alert.addTextField { $0.text = defaultText }
            alert.addAction(UIAlertAction(title: "Batal", style: .cancel) { _ in completionHandler(nil) })
            alert.addAction(UIAlertAction(title: "OK", style: .default) { _ in
                completionHandler(alert.textFields?.first?.text)
            })
            presenter.present(alert, animated: true)
        }

        // target="_blank" / window.open
        func webView(_ webView: WKWebView, createWebViewWith configuration: WKWebViewConfiguration, for navigationAction: WKNavigationAction, windowFeatures: WKWindowFeatures) -> WKWebView? {
            if navigationAction.targetFrame == nil, let url = navigationAction.request.url {
                let host = (url.host ?? "").lowercased()
                if host == AppConfig.appHost.lowercased() {
                    webView.load(navigationAction.request)
                } else if UIApplication.shared.canOpenURL(url) {
                    UIApplication.shared.open(url)
                }
            }
            return nil
        }

        // MARK: Biometric JavaScript bridge

        func userContentController(_ userContentController: WKUserContentController, didReceive message: WKScriptMessage) {
            guard message.name == "biometric" else { return }
            let action: String
            if let body = message.body as? [String: Any] {
                action = String(describing: body["action"] ?? "")
            } else {
                action = String(describing: message.body)
            }

            switch action {
            case "enable":
                model.biometrics.requestEnable()
            case "disable":
                model.biometrics.requestDisable()
            default:
                sendBiometricStatus()
            }
        }

        private func sendBiometricStatus() {
            guard let webView else { return }
            let objectJSON = model.biometrics.statusJSON()
            let js = """
            (function(){
              var s = \(objectJSON);
              window.__ckBiometricStatus = JSON.stringify(s);
              window.dispatchEvent(new CustomEvent('finance-biometric-status',{detail:s}));
            })();
            """
            webView.evaluateJavaScript(js, completionHandler: nil)
        }

        private func hideInstallPrompts() {
            webView?.evaluateJavaScript("""
            (function(){
              var ids=['iosInstallArea','androidInstallArea','desktopInstallArea'];
              ids.forEach(function(id){var el=document.getElementById(id);if(el)el.hidden=true;});
            })();
            """, completionHandler: nil)
        }

        // MARK: Offline

        private func showOfflineFallback(in webView: WKWebView) {
            guard !showingOfflinePage,
                  let url = Bundle.main.url(forResource: "Offline", withExtension: "html") else { return }
            showingOfflinePage = true
            webView.loadFileURL(url, allowingReadAccessTo: url.deletingLastPathComponent())
        }

        // MARK: Downloads

        @available(iOS 14.5, *)
        func webView(_ webView: WKWebView, navigationAction: WKNavigationAction, didBecome download: WKDownload) {
            download.delegate = self
        }

        @available(iOS 14.5, *)
        func webView(_ webView: WKWebView, navigationResponse: WKNavigationResponse, didBecome download: WKDownload) {
            download.delegate = self
        }

        @available(iOS 14.5, *)
        func download(
            _ download: WKDownload,
            decideDestinationUsing response: URLResponse,
            suggestedFilename: String,
            completionHandler: @escaping (URL?) -> Void
        ) {
            let safeFilename = suggestedFilename.replacingOccurrences(of: "/", with: "-")
            let destination = FileManager.default.temporaryDirectory.appendingPathComponent(safeFilename)
            try? FileManager.default.removeItem(at: destination)
            downloadDestinations[ObjectIdentifier(download)] = destination
            completionHandler(destination)
        }

        @available(iOS 14.5, *)
        func downloadDidFinish(_ download: WKDownload) {
            guard let url = downloadDestinations.removeValue(forKey: ObjectIdentifier(download)) else { return }
            presentExportSheet(for: url)
        }

        @available(iOS 14.5, *)
        func download(_ download: WKDownload, didFailWithError error: Error, resumeData: Data?) {
            downloadDestinations.removeValue(forKey: ObjectIdentifier(download))
            guard let presenter = UIApplication.shared.financeTopViewController else { return }
            let alert = UIAlertController(title: "Download gagal", message: error.localizedDescription, preferredStyle: .alert)
            alert.addAction(UIAlertAction(title: "OK", style: .default))
            presenter.present(alert, animated: true)
        }

        private func presentExportSheet(for url: URL) {
            guard let presenter = UIApplication.shared.financeTopViewController else { return }
            let picker = UIDocumentPickerViewController(forExporting: [url], asCopy: true)
            presenter.present(picker, animated: true)
        }
    }
}

private extension UIApplication {
    var financeTopViewController: UIViewController? {
        let scenes = connectedScenes.compactMap { $0 as? UIWindowScene }
        let root = scenes.flatMap(\.windows).first(where: { $0.isKeyWindow })?.rootViewController
        return top(from: root)
    }

    func top(from controller: UIViewController?) -> UIViewController? {
        if let navigation = controller as? UINavigationController {
            return top(from: navigation.visibleViewController)
        }
        if let tab = controller as? UITabBarController {
            return top(from: tab.selectedViewController)
        }
        if let presented = controller?.presentedViewController {
            return top(from: presented)
        }
        return controller
    }
}
