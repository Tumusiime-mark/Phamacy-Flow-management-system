' Pharmacy Management System - Client Listener
Option Explicit
Dim objShell, objFSO, strServerIP, intServerPort, strURL, objHTTP, bConnected

Set objShell = CreateObject("WScript.Shell")
Set objFSO = CreateObject("Scripting.FileSystemObject")

' Get the pharmacy directory
Dim strPharmacyDir
strPharmacyDir = objFSO.GetParentFolderName(WScript.ScriptFullName)

' Load configuration from file if exists
Dim configFile, defaultIP, defaultPort
configFile = strPharmacyDir & "\client-config.ini"
defaultIP = "192.168.168.164"
defaultPort = "8000"

If objFSO.FileExists(configFile) Then
    Dim fsoFile, strConfig
    Set fsoFile = objFSO.OpenTextFile(configFile, 1)
    strConfig = fsoFile.ReadAll()
    fsoFile.Close()
    
    Dim arrConfig
    arrConfig = Split(strConfig, vbCrLf)
    Dim i
    For i = 0 To UBound(arrConfig)
        If InStr(arrConfig(i), "IP=") > 0 Then
            defaultIP = Trim(Replace(arrConfig(i), "IP=", ""))
        End If
        If InStr(arrConfig(i), "PORT=") > 0 Then
            defaultPort = Trim(Replace(arrConfig(i), "PORT=", ""))
        End If
    Next
End If

strServerIP = defaultIP
intServerPort = defaultPort

' Create IE COM object for GUI forms
Dim IE, strHTML

Set IE = CreateObject("InternetExplorer.Application")
IE.Visible = True
IE.ToolBar = False
IE.StatusBar = False
IE.MenuBar = False
IE.Resizable = False

' Create HTML form for configuration
strHTML = "<!DOCTYPE html>" & vbCrLf & _
"<html>" & vbCrLf & _
"<head>" & vbCrLf & _
"<title>PHARMACY MANAGEMENT SYSTEM - CLIENT</title>" & vbCrLf & _
"<style>" & vbCrLf & _
"body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }" & vbCrLf & _
".container { background: white; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); width: 450px; padding: 40px; }" & vbCrLf & _
".header { text-align: center; margin-bottom: 30px; }" & vbCrLf & _
".header h1 { color: #f5576c; margin: 0; font-size: 24px; }" & vbCrLf & _
".header p { color: #666; margin: 5px 0; font-size: 12px; }" & vbCrLf & _
".form-group { margin-bottom: 20px; }" & vbCrLf & _
"label { display: block; margin-bottom: 8px; color: #333; font-weight: bold; }" & vbCrLf & _
"input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 14px; }" & vbCrLf & _
"input:focus { outline: none; border-color: #f5576c; box-shadow: 0 0 5px rgba(245, 87, 108, 0.3); }" & vbCrLf & _
".status { margin: 20px 0; padding: 15px; border-radius: 5px; text-align: center; font-weight: bold; display: none; }" & vbCrLf & _
".status.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }" & vbCrLf & _
".status.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }" & vbCrLf & _
".status.connecting { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }" & vbCrLf & _
".button-group { display: flex; gap: 10px; margin-top: 30px; }" & vbCrLf & _
"button { flex: 1; padding: 12px; border: none; border-radius: 5px; font-size: 14px; font-weight: bold; cursor: pointer; transition: all 0.3s; }" & vbCrLf & _
".btn-connect { background: #f5576c; color: white; }" & vbCrLf & _
".btn-connect:hover { background: #f04452; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(245, 87, 108, 0.4); }" & vbCrLf & _
".btn-cancel { background: #ddd; color: #333; }" & vbCrLf & _
".btn-cancel:hover { background: #ccc; }" & vbCrLf & _
".contact { text-align: center; margin-top: 20px; font-size: 11px; color: #999; border-top: 1px solid #eee; padding-top: 15px; }" & vbCrLf & _
".contact a { color: #f5576c; text-decoration: none; }" & vbCrLf & _
"</style>" & vbCrLf & _
"</head>" & vbCrLf & _
"<body>" & vbCrLf & _
"<div class='container'>" & vbCrLf & _
"<div class='header'>" & vbCrLf & _
"<h1>PHARMACY MANAGEMENT SYSTEM</h1>" & vbCrLf & _
"<p>Client Access</p>" & vbCrLf & _
"<p>Developed by Mark T Crafts Tech Ltd</p>" & vbCrLf & _
"</div>" & vbCrLf & _
"<form id='configForm'>" & vbCrLf & _
"<div class='form-group'>" & vbCrLf & _
"<label for='ip'>Server IP Address:</label>" & vbCrLf & _
"<input type='text' id='ip' name='ip' value='" & strServerIP & "' required>" & vbCrLf & _
"</div>" & vbCrLf & _
"<div class='form-group'>" & vbCrLf & _
"<label for='port'>Server Port:</label>" & vbCrLf & _
"<input type='text' id='port' name='port' value='" & intServerPort & "' required>" & vbCrLf & _
"</div>" & vbCrLf & _
"<div id='status' class='status'></div>" & vbCrLf & _
"<div class='button-group'>" & vbCrLf & _
"<button type='button' class='btn-connect' onclick='connectToServer()'>Connect & Open</button>" & vbCrLf & _
"<button type='button' class='btn-cancel' onclick='window.close()'>Cancel</button>" & vbCrLf & _
"</div>" & vbCrLf & _
"<div class='contact'>" & vbCrLf & _
"<p>For assistance, contact:<br><strong>Phone:</strong> 0780427684<br><strong>Email:</strong> <a href='mailto:tumusiimemac@gmail.com'>tumusiimemac@gmail.com</a></p>" & vbCrLf & _
"</div>" & vbCrLf & _
"</form>" & vbCrLf & _
"</div>" & vbCrLf & _
"<script>" & vbCrLf & _
"var connectionCheckInterval = null;" & vbCrLf & _
"function connectToServer() {" & vbCrLf & _
"  var ip = document.getElementById('ip').value;" & vbCrLf & _
"  var port = document.getElementById('port').value;" & vbCrLf & _
"  if (!ip || !port) {" & vbCrLf & _
"    alert('Please fill in all fields');" & vbCrLf & _
"    return;" & vbCrLf & _
"  }" & vbCrLf & _
"  showStatus('Connecting to server...', 'connecting');" & vbCrLf & _
"  window.external.ConnectToServer(ip, port);" & vbCrLf & _
"}" & vbCrLf & _
"function showStatus(msg, type) {" & vbCrLf & _
"  var statusDiv = document.getElementById('status');" & vbCrLf & _
"  statusDiv.innerHTML = msg;" & vbCrLf & _
"  statusDiv.className = 'status ' + type;" & vbCrLf & _
"  statusDiv.style.display = 'block';" & vbCrLf & _
"}" & vbCrLf & _
"function setSuccessStatus() {" & vbCrLf & _
"  showStatus('✓ Successfully connected! Opening website...', 'success');" & vbCrLf & _
"  setTimeout(function() { window.close(); }, 2000);" & vbCrLf & _
"}" & vbCrLf & _
"function setErrorStatus() {" & vbCrLf & _
"  showStatus('✗ Connection failed. Please check the server IP and port.', 'error');" & vbCrLf & _
"}" & vbCrLf & _
"</script>" & vbCrLf & _
"</body>" & vbCrLf & _
"</html>"

' Load HTML into IE
IE.Navigate "about:blank"
While IE.Busy Or IE.ReadyState <> 4
    WScript.Sleep 100
Wend

Dim doc
Set doc = IE.Document
doc.Write strHTML
doc.Close

' Wait for user action
Do While True
    If objFSO.FileExists(strPharmacyDir & "\connect-client.flag") Then
        ' Read the configuration
        Dim flagFile
        Set flagFile = objFSO.OpenTextFile(strPharmacyDir & "\connect-client.flag", 1)
        Dim flagContent
        flagContent = flagFile.ReadAll()
        flagFile.Close()
        objFSO.DeleteFile strPharmacyDir & "\connect-client.flag", True
        
        ' Parse IP and Port from flag file
        Dim arrFlag
        arrFlag = Split(flagContent, "|")
        If UBound(arrFlag) >= 1 Then
            strServerIP = Trim(arrFlag(0))
            intServerPort = Trim(arrFlag(1))
        End If
        
        ' Save configuration
        Dim configOutput
        Set configOutput = objFSO.CreateTextFile(configFile, True)
        configOutput.WriteLine "IP=" & strServerIP
        configOutput.WriteLine "PORT=" & intServerPort
        configOutput.Close()
        
        ' Test connection and open browser
        strURL = "http://" & strServerIP & ":" & intServerPort
        
        ' Try to open URL - if successful, close window after delay
        On Error Resume Next
        objShell.Run strURL
        
        ' Close IE window
        IE.Quit
        
        ' Keep monitoring connection for a while
        MonitorConnection strServerIP, intServerPort
        
        Exit Do
    End If
    
    ' Check if IE is still open
    On Error Resume Next
    If Err <> 0 Or IE Is Nothing Then
        Exit Do
    End If
    On Error Goto 0
    
    WScript.Sleep 200
Loop

Sub MonitorConnection(ip, port)
    Dim strURL, objHTTP, bConnected, intRetries
    strURL = "http://" & ip & ":" & port
    intRetries = 0
    bConnected = False
    
    ' Try to connect for up to 10 seconds
    Do While intRetries < 10
        On Error Resume Next
        Set objHTTP = CreateObject("MSXML2.XMLHTTP")
        objHTTP.Open "HEAD", strURL, False
        objHTTP.Send
        
        If Err = 0 And objHTTP.Status = 200 Then
            bConnected = True
            Exit Do
        End If
        On Error Goto 0
        
        Set objHTTP = Nothing
        intRetries = intRetries + 1
        WScript.Sleep 1000
    Loop
End Sub
