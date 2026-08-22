import java.net.URI

plugins {
    id("com.android.application")
}

val siteUrl = providers.gradleProperty("SMESSAGES_SITE_URL")
    .orElse("https://example.com/")
    .get()

require(siteUrl.startsWith("https://")) { "SMESSAGES_SITE_URL must use HTTPS" }
requireNotNull(URI(siteUrl).host) { "SMESSAGES_SITE_URL must be a valid absolute URL" }

android {
    namespace = "com.smessages.securewrapper"
    compileSdk = 37

    defaultConfig {
        applicationId = "com.smessages.securewrapper"
        minSdk = 23
        targetSdk = 37
        versionCode = 1
        versionName = "1.0.0"
        buildConfigField("String", "SITE_URL", "\"${siteUrl.replace("\\", "\\\\").replace("\"", "\\\"")}\"")
    }

    buildFeatures { buildConfig = true }

    buildTypes {
        release {
            isMinifyEnabled = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
}
