import SwiftUI

struct SplashView: View {
    var body: some View {
        ZStack {
            Color(red: 0.965, green: 0.976, blue: 0.995)
                .ignoresSafeArea()

            VStack(spacing: 18) {
                Image("LaunchLogo")
                    .resizable()
                    .scaledToFit()
                    .frame(width: 92, height: 92)
                    .clipShape(RoundedRectangle(cornerRadius: 24, style: .continuous))
                    .shadow(color: .black.opacity(0.08), radius: 18, y: 8)

                VStack(spacing: 6) {
                    Text("Catatan Keuangan")
                        .font(.system(size: 24, weight: .bold, design: .rounded))
                        .foregroundStyle(Color(red: 0.09, green: 0.13, blue: 0.21))
                    Text("Asisten keuangan pribadi")
                        .font(.system(size: 14, weight: .medium))
                        .foregroundStyle(.secondary)
                }
            }
        }
    }
}
