# Pharmacy Management System - Client Access
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Net.Http
[System.Windows.Forms.Application]::EnableVisualStyles()

$PharmacyDir = Split-Path -Parent $MyInvocation.MyCommandPath
$ConfigFile = "$PharmacyDir\client-config.ini"

# Load configuration
$IP = "192.168.168.164"
$Port = "8000"
$ConnectionStatus = "Not Connected"

if (Test-Path $ConfigFile) {
    $Config = @{}
    Get-Content $ConfigFile | ForEach-Object {
        $parts = $_ -split '='
        if ($parts.Count -eq 2) {
            $Config[$parts[0].Trim()] = $parts[1].Trim()
        }
    }
    if ($Config.ContainsKey('IP')) { $IP = $Config['IP'] }
    if ($Config.ContainsKey('PORT')) { $Port = $Config['PORT'] }
}

# Create Form
$form = New-Object System.Windows.Forms.Form
$form.Text = "PHARMACY MANAGEMENT SYSTEM - CLIENT"
$form.Width = 450
$form.Height = 450
$form.StartPosition = [System.Windows.Forms.FormStartPosition]::CenterScreen
$form.MaximizeBox = $false
$form.MinimizeBox = $false
$form.FormBorderStyle = [System.Windows.Forms.FormBorderStyle]::Fixed3D

# Header Panel
$headerPanel = New-Object System.Windows.Forms.Panel
$headerPanel.BackColor = [System.Drawing.Color]::FromArgb(245, 87, 108)
$headerPanel.Dock = [System.Windows.Forms.DockStyle]::Top
$headerPanel.Height = 80

$titleLabel = New-Object System.Windows.Forms.Label
$titleLabel.Text = "PHARMACY MANAGEMENT SYSTEM`nClient Access"
$titleLabel.ForeColor = [System.Drawing.Color]::White
$titleLabel.Font = New-Object System.Drawing.Font("Arial", 12, [System.Drawing.FontStyle]::Bold)
$titleLabel.Dock = [System.Windows.Forms.DockStyle]::Fill
$titleLabel.TextAlign = [System.Drawing.ContentAlignment]::MiddleCenter
$headerPanel.Controls.Add($titleLabel)

$form.Controls.Add($headerPanel)

# Content Panel
$contentPanel = New-Object System.Windows.Forms.Panel
$contentPanel.Dock = [System.Windows.Forms.DockStyle]::Fill
$contentPanel.Padding = [System.Windows.Forms.Padding]::new(20)

# IP Label
$ipLabel = New-Object System.Windows.Forms.Label
$ipLabel.Text = "Server IP Address:"
$ipLabel.Location = New-Object System.Drawing.Point(10, 20)
$ipLabel.Size = New-Object System.Drawing.Size(200, 20)
$ipLabel.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$contentPanel.Controls.Add($ipLabel)

# IP TextBox
$ipTextBox = New-Object System.Windows.Forms.TextBox
$ipTextBox.Text = $IP
$ipTextBox.Location = New-Object System.Drawing.Point(10, 45)
$ipTextBox.Size = New-Object System.Drawing.Size(380, 30)
$contentPanel.Controls.Add($ipTextBox)

# Port Label
$portLabel = New-Object System.Windows.Forms.Label
$portLabel.Text = "Server Port:"
$portLabel.Location = New-Object System.Drawing.Point(10, 85)
$portLabel.Size = New-Object System.Drawing.Size(200, 20)
$portLabel.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$contentPanel.Controls.Add($portLabel)

# Port TextBox
$portTextBox = New-Object System.Windows.Forms.TextBox
$portTextBox.Text = $Port
$portTextBox.Location = New-Object System.Drawing.Point(10, 110)
$portTextBox.Size = New-Object System.Drawing.Size(380, 30)
$contentPanel.Controls.Add($portTextBox)

# Status Label
$statusLabel = New-Object System.Windows.Forms.Label
$statusLabel.Text = "Status: Not Connected"
$statusLabel.Location = New-Object System.Drawing.Point(10, 150)
$statusLabel.Size = New-Object System.Drawing.Size(380, 25)
$statusLabel.Font = New-Object System.Drawing.Font("Arial", 10)
$statusLabel.ForeColor = [System.Drawing.Color]::Orange
$contentPanel.Controls.Add($statusLabel)

# Connect Button
$connectBtn = New-Object System.Windows.Forms.Button
$connectBtn.Text = "Connect & Open"
$connectBtn.Location = New-Object System.Drawing.Point(10, 185)
$connectBtn.Size = New-Object System.Drawing.Size(180, 40)
$connectBtn.BackColor = [System.Drawing.Color]::FromArgb(245, 87, 108)
$connectBtn.ForeColor = [System.Drawing.Color]::White
$connectBtn.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$connectBtn.Cursor = [System.Windows.Forms.Cursors]::Hand

$connectBtn.Add_Click({
    $script:IP = $ipTextBox.Text.Trim()
    $script:Port = $portTextBox.Text.Trim()
    
    if ([string]::IsNullOrWhiteSpace($script:IP) -or [string]::IsNullOrWhiteSpace($script:Port)) {
        $statusLabel.Text = "Status: Please enter IP and Port"
        $statusLabel.ForeColor = [System.Drawing.Color]::Red
        return
    }
    
    $statusLabel.Text = "Status: Connecting..."
    $statusLabel.ForeColor = [System.Drawing.Color]::Orange
    $form.Refresh()
    
    # Test connection
    $url = "http://$script:IP`:$script:Port"
    $connected = $false
    
    try {
        $response = Invoke-WebRequest -Uri $url -TimeoutSec 3 -ErrorAction Stop
        $connected = $true
    } catch {
        $connected = $false
    }
    
    if ($connected) {
        # Save configuration
        $config = @(
            "IP=$script:IP"
            "PORT=$script:Port"
        )
        $config | Set-Content $ConfigFile -Force
        
        $statusLabel.Text = "Status: Connected Successfully!"
        $statusLabel.ForeColor = [System.Drawing.Color]::Green
        $form.Refresh()
        
        # Open browser
        Start-Process $url
        
        # Close after 2 seconds
        Start-Sleep -Seconds 2
        $form.Close()
    } else {
        $statusLabel.Text = "Status: Connection Failed - Server not responding"
        $statusLabel.ForeColor = [System.Drawing.Color]::Red
    }
})

$contentPanel.Controls.Add($connectBtn)

# Cancel Button
$cancelBtn = New-Object System.Windows.Forms.Button
$cancelBtn.Text = "Cancel"
$cancelBtn.Location = New-Object System.Drawing.Point(210, 185)
$cancelBtn.Size = New-Object System.Drawing.Size(180, 40)
$cancelBtn.BackColor = [System.Drawing.Color]::LightGray
$cancelBtn.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$cancelBtn.Cursor = [System.Windows.Forms.Cursors]::Hand

$cancelBtn.Add_Click({
    $form.Close()
})

$contentPanel.Controls.Add($cancelBtn)

# Contact Info
$contactLabel = New-Object System.Windows.Forms.Label
$contactLabel.Text = "Developed by Mark T Crafts Tech Ltd`nPhone: 0780427684`nEmail: tumusiimemac@gmail.com"
$contactLabel.Location = New-Object System.Drawing.Point(10, 240)
$contactLabel.Size = New-Object System.Drawing.Size(380, 60)
$contactLabel.Font = New-Object System.Drawing.Font("Arial", 9)
$contactLabel.ForeColor = [System.Drawing.Color]::DarkGray
$contactLabel.TextAlign = [System.Drawing.ContentAlignment]::TopCenter
$contentPanel.Controls.Add($contactLabel)

$form.Controls.Add($contentPanel)

# Show form
$form.ShowDialog() | Out-Null

exit
