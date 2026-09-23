import SwiftUI

struct ContentView: View {
    @StateObject private var model = AppModel()
    @Environment(\.scenePhase) private var scenePhase

    var body: some View {
        ZStack(alignment: .top) {
            FinanceWebView(model: model, isConnected: model.network.isConnected)
                .ignoresSafeArea(.container, edges: .bottom)

            if model.pageLoading && !model.showSplash {
                ProgressView(value: model.pageProgress)
                    .progressViewStyle(.linear)
                    .tint(Color(red: 0.09, green: 0.36, blue: 0.83))
                    .frame(maxWidth: .infinity)
                    .zIndex(10)
            }

            if model.biometrics.isLocked && !model.showSplash {
                BiometricLockView(manager: model.biometrics) {
                    model.useApplicationPin()
                }
                .zIndex(20)
            }

            if model.showSplash {
                SplashView()
                    .transition(.opacity)
                    .zIndex(30)
            }
        }
        .task {
            try? await Task.sleep(nanoseconds: 900_000_000)
            withAnimation(.easeOut(duration: 0.18)) {
                model.finishSplashAndUnlockIfNeeded()
            }
        }
        .onChange(of: model.network.isConnected) { connected in
            if connected {
                model.notifyWebOnline()
            }
        }
        .onChange(of: scenePhase) { phase in
            switch phase {
            case .background:
                model.sceneDidEnterBackground()
            case .active:
                model.sceneDidBecomeActive()
                if model.network.isConnected { model.notifyWebOnline() }
            default:
                break
            }
        }
    }
}
