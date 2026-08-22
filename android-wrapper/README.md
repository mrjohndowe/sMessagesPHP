# Secure Messages Android wrapper

This native Android WebView wrapper opens the hosted sMessagesPHP site and marks
the app window as secure. Android blocks screenshots, screen recording,
recent-app previews, and output to non-secure displays.

## Configure

Edit `SMESSAGES_SITE_URL` in `gradle.properties` and set the HTTPS URL of your
deployed sMessagesPHP installation:

```properties
SMESSAGES_SITE_URL=https://messages.example.com/
```

The wrapper only permits that exact HTTPS host and port inside the WebView.
Other HTTP or HTTPS links open in the device browser.

## Build

1. Open the `android-wrapper` folder in Android Studio.
2. Allow Gradle synchronization to complete.
3. Select **Build > Build APK(s)** for a test APK.
4. For distribution, select **Build > Generate Signed App Bundle or APK**.

The project requires JDK 17, Android SDK 37, and Android Build Tools 36.0.0.
The debug APK is generated under `app/build/outputs/apk/debug/`.

## Security behavior

- `FLAG_SECURE` is enabled before the WebView is created.
- Cleartext HTTP and mixed content are disabled.
- File-system and content-provider access from web pages are disabled.
- Third-party cookies are disabled.
- No JavaScript-to-native bridge is exposed.
- The system file picker supports the website attachment field.

`FLAG_SECURE` cannot stop someone photographing the physical screen with a
second device.
