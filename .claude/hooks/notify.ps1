# Waiting for input: show a Windows toast notification.
# (ASCII only: Windows PowerShell 5.1 misreads UTF-8 files without BOM.)
$ErrorActionPreference = 'Stop'

$message = 'Waiting for your reply'
try {
    [Console]::InputEncoding = [System.Text.Encoding]::UTF8
    $raw = [Console]::In.ReadToEnd()
    if ($raw) {
        $data = $raw | ConvertFrom-Json
        if ($data.message) { $message = [string]$data.message }
    }
} catch {}

$title = 'Claude Code'
$esc = [System.Security.SecurityElement]

try {
    [Windows.UI.Notifications.ToastNotificationManager, Windows.UI.Notifications, ContentType = WindowsRuntime] | Out-Null
    [Windows.Data.Xml.Dom.XmlDocument, Windows.Data.Xml.Dom.XmlDocument, ContentType = WindowsRuntime] | Out-Null

    $xml = New-Object Windows.Data.Xml.Dom.XmlDocument
    $xml.LoadXml("<toast><visual><binding template='ToastGeneric'><text>$($esc::Escape($title))</text><text>$($esc::Escape($message))</text></binding></visual></toast>")
    $toast = New-Object Windows.UI.Notifications.ToastNotification $xml
    $appId = '{1AC14E77-02E7-4E5D-B744-2EB1AE5198B7}\WindowsPowerShell\v1.0\powershell.exe'
    [Windows.UI.Notifications.ToastNotificationManager]::CreateToastNotifier($appId).Show($toast)
} catch {
    # Fallback: balloon tip in the notification area
    Add-Type -AssemblyName System.Windows.Forms
    $icon = New-Object System.Windows.Forms.NotifyIcon
    $icon.Icon = [System.Drawing.SystemIcons]::Information
    $icon.Visible = $true
    $icon.ShowBalloonTip(5000, $title, $message, 'Info')
    Start-Sleep -Seconds 6
    $icon.Dispose()
}
