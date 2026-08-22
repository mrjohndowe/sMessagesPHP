# Secure message. Share important information via untrusted sources securely.

## General description
This small simple PHP script allows you to secure share any text data via any method you want by only once opening links. It works simply:
- You type text and than it is being encrypted by SHA256 algorythm and stored in MySQL database.
- After that you get an uniq URL link to this encrypted data. This link is alive only during time you have selected.1 hour default.
- You share this link via any messanger. The messenger can't see your data while creating a preview of the typed link.
- The receiver opens the link. 
    - If everything is ok, he sees a confirmation dialog to show the encrypted data.After confirmation, the receiver sees your text.The link can't be opened anymore.
    - If somebody already opened the message - the receiver will see an error message that this message doesn't exist anymore.That means your data is stolen and you must quickliy change it.And the messenger is not secure anymore.
- The script includes file "lang.php" which contains 3x language translations - UA, RU, EN. Locale is autodetected by headers from your web browser.
- Also the script uses some Bootstrap files.Just to look better then it can be.

## Installation
* The script requires MySQL database. You need to create database and user/pass for it. After that, upload the scheme from scheme.sql file.
* After first launch, the script will generate config.php file. After that you need to setup DB credentials and encryption key inside the config.php.Encryption key is used to encrypt messages inside the DB, you can set any password you want.
* After the second step is completed, you will see working page of the script.Here we go.

## Small tips
* Created links has an exparition timeout.After the exparation you woudn't be able to open this messages using their links.When you try - the message will be selfdestroyed and you will be notified about.
* You can set CRON to launch autoclean function from the script, which will find and clean all expired messages. The function can be called by adding GET request "?clean" to your address.
* There are two tables in DB - one for temporarily store messages, second for logging. You can check logging table to see when your message was opened and by which IP.

## Android wrapper

An optional native Android WebView application is available in
`android-wrapper`. It uses Android secure-window protection to block
screenshots, screen recording, recent-app previews, and non-secure displays.
See `android-wrapper/README.md` for URL configuration and build instructions.
