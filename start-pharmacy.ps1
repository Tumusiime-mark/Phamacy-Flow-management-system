# Pharmacy Management System - Server Launcher
Add-Type -AssemblyName System.Windows.Forms
[System.Windows.Forms.Application]::EnableVisualStyles()

# Get script directory (handle both direct execution and batch file execution)
if ($PSScriptRoot) {
    $PharmacyDir = $PSScriptRoot
} elseif ($MyInvocation.MyCommand.Path) {
    $PharmacyDir = Split-Path -Parent $MyInvocation.MyCommand.Path
} else {
    # Fallback for batch file execution
    $PharmacyDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
}

$ConfigFile = "$PharmacyDir\pharmacy-config.ini"

# Load configuration
$IP = "192.168.168.164"
$Port = "8000"

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
$form.Text = "PHARMACY MANAGEMENT SYSTEM"
$form.Width = 450
$form.Height = 450
$form.StartPosition = [System.Windows.Forms.FormStartPosition]::CenterScreen
$form.MaximizeBox = $false
$form.MinimizeBox = $false
$form.FormBorderStyle = [System.Windows.Forms.FormBorderStyle]::Fixed3D

# Header Panel
$headerPanel = New-Object System.Windows.Forms.Panel
$headerPanel.BackColor = [System.Drawing.Color]::FromArgb(102, 126, 234)
$headerPanel.Dock = [System.Windows.Forms.DockStyle]::Top
$headerPanel.Height = 80

$titleLabel = New-Object System.Windows.Forms.Label
$titleLabel.Text = "PHARMACY MANAGEMENT SYSTEM"
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

# Start Button
$startBtn = New-Object System.Windows.Forms.Button
$startBtn.Text = "Start Server"
$startBtn.Location = New-Object System.Drawing.Point(10, 160)
$startBtn.Size = New-Object System.Drawing.Size(180, 40)
$startBtn.BackColor = [System.Drawing.Color]::FromArgb(102, 126, 234)
$startBtn.ForeColor = [System.Drawing.Color]::White
$startBtn.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$startBtn.Cursor = [System.Windows.Forms.Cursors]::Hand

$startBtn.Add_Click({
    $script:IP = $ipTextBox.Text.Trim()
    $script:Port = $portTextBox.Text.Trim()
    
    if ([string]::IsNullOrWhiteSpace($script:IP) -or [string]::IsNullOrWhiteSpace($script:Port)) {
        [System.Windows.Forms.MessageBox]::Show("Please enter both IP and Port", "Input Required", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Warning)
        return
    }
    
    $form.DialogResult = [System.Windows.Forms.DialogResult]::OK
    $form.Close()
})

$contentPanel.Controls.Add($startBtn)

# Cancel Button
$cancelBtn = New-Object System.Windows.Forms.Button
$cancelBtn.Text = "Cancel"
$cancelBtn.Location = New-Object System.Drawing.Point(210, 160)
$cancelBtn.Size = New-Object System.Drawing.Size(180, 40)
$cancelBtn.BackColor = [System.Drawing.Color]::LightGray
$cancelBtn.Font = New-Object System.Drawing.Font("Arial", 10, [System.Drawing.FontStyle]::Bold)
$cancelBtn.Cursor = [System.Windows.Forms.Cursors]::Hand

$cancelBtn.Add_Click({
    $form.DialogResult = [System.Windows.Forms.DialogResult]::Cancel
    $form.Close()
})

$contentPanel.Controls.Add($cancelBtn)

# Contact Info
$contactLabel = New-Object System.Windows.Forms.Label
$contactLabel.Text = "Developed by Mark T Crafts Tech Ltd`nPhone: 0780427684`nEmail: tumusiimemac@gmail.com"
$contactLabel.Location = New-Object System.Drawing.Point(10, 220)
$contactLabel.Size = New-Object System.Drawing.Size(380, 60)
$contactLabel.Font = New-Object System.Drawing.Font("Arial", 9)
$contactLabel.ForeColor = [System.Drawing.Color]::DarkGray
$contactLabel.TextAlign = [System.Drawing.ContentAlignment]::TopCenter
$contentPanel.Controls.Add($contactLabel)

# Notice Label
$noticeLabel = New-Object System.Windows.Forms.Label
$noticeLabel.Text = "NOTICE: Ensure PHP is installed and accessible from command line. Server will run in background and open browser automatically."
$noticeLabel.Location = New-Object System.Drawing.Point(10, 285)
$noticeLabel.Size = New-Object System.Drawing.Size(380, 40)
$noticeLabel.Font = New-Object System.Drawing.Font("Arial", 8)
$noticeLabel.ForeColor = [System.Drawing.Color]::Blue
$noticeLabel.TextAlign = [System.Drawing.ContentAlignment]::TopCenter
$contentPanel.Controls.Add($noticeLabel)

$form.Controls.Add($contentPanel)

# Show form
$result = $form.ShowDialog()

if ($result -eq [System.Windows.Forms.DialogResult]::OK) {
    # Save configuration
    $config = @(
        "IP=$IP"
        "PORT=$Port"
    )
    $config | Set-Content $ConfigFile -Force
    
    # Start PHP server in background
    $url = "http://$IP`:$Port"
    
    # Start PHP server (hidden window)
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = "php"
    $psi.Arguments = "-S `"$IP`:$Port`""
    $psi.WorkingDirectory = $PharmacyDir
    $psi.WindowStyle = [System.Diagnostics.ProcessWindowStyle]::Hidden
    $psi.CreateNoWindow = $true
    
    [System.Diagnostics.Process]::Start($psi) | Out-Null
    
    # Wait for server to start
    Start-Sleep -Seconds 2
    
    # Open browser
    Start-Process $url
}

exit
