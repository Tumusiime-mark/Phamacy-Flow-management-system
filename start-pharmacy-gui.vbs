' Pharmacy Management System - Server GUI
Option Explicit
Dim objShell, objFSO, strIP, intPort, strURL
Dim objIE, strHTML, doc, strPharmacyDir, configFile

Set objShell = CreateObject("WScript.Shell")
Set objFSO = CreateObject("Scripting.FileSystemObject")

strPharmacyDir = objFSO.GetParentFolderName(WScript.ScriptFullName)
configFile = strPharmacyDir & "\pharmacy-config.ini"

' Load or set defaults
strIP = "192.168.168.164"
intPort = "8000"

If objFSO.FileExists(configFile) Then
    Dim fsoFile, arrLines, i
    Set fsoFile = objFSO.OpenTextFile(configFile, 1)
    Do While Not fsoFile.AtEndOfStream
        Dim line
        line = fsoFile.ReadLine()
        If InStr(line, "IP=") = 1 Then
            strIP = Mid(line, 4)
        End If
        If InStr(line, "PORT=") = 1 Then
            intPort = Mid(line, 6)
        End If
    Loop
    fsoFile.Close()
End If

' Create and show IE GUI
Set objIE = CreateObject("InternetExplorer.Application")
objIE.Visible = True
objIE.ToolBar = False
objIE.StatusBar = False
objIE.MenuBar = False
objIE.Resizable = False

' Center window approximately
objIE.Width = 500
objIE.Height = 550
objIE.Left = (objShell.RegRead("HKEY_CURRENT_USER\Control Panel\Desktop\WindowMetrics\CaptionWidth") * 2 + 1024) / 2 - 250

strHTML = "<!DOCTYPE html>" & vbCrLf & _
"<html><head>" & vbCrLf & _
"<meta charset='UTF-8'>" & vbCrLf & _
"<title>PHARMACY MANAGEMENT SYSTEM</title>" & vbCrLf & _
"<style>" & vbCrLf & _
"* { margin: 0; padding: 0; box-sizing: border-box; }" & vbCrLf & _
"body { " & vbCrLf & _
"  font-family: 'Segoe UI', Arial, sans-serif; " & vbCrLf & _
"  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); " & vbCrLf & _
"  display: flex; justify-content: center; align-items: center; " & vbCrLf & _
"  min-height: 100vh; padding: 20px; " & vbCrLf & _
"}" & vbCrLf & _
".container { " & vbCrLf & _
"  background: white; " & vbCrLf & _
"  border-radius: 10px; " & vbCrLf & _
"  box-shadow: 0 10px 40px rgba(0,0,0,0.3); " & vbCrLf & _
"  padding: 40px; " & vbCrLf & _
"  width: 100%; " & vbCrLf & _
"  max-width: 400px; " & vbCrLf & _
"}" & vbCrLf & _
".header { text-align: center; margin-bottom: 30px; }" & vbCrLf & _
".header h1 { color: #667eea; font-size: 22px; margin-bottom: 10px; }" & vbCrLf & _
".header p { color: #666; font-size: 12px; margin: 3px 0; }" & vbCrLf & _
".form-group { margin-bottom: 20px; }" & vbCrLf & _
"label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; font-size: 13px; }" & vbCrLf & _
"input { " & vbCrLf & _
"  width: 100%; " & vbCrLf & _
"  padding: 10px 12px; " & vbCrLf & _
"  border: 1px solid #ddd; " & vbCrLf & _
"  border-radius: 5px; " & vbCrLf & _
"  font-size: 14px; " & vbCrLf & _
"  transition: all 0.3s; " & vbCrLf & _
"}" & vbCrLf & _
"input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 5px rgba(102,126,234,0.3); }" & vbCrLf & _
".button-group { display: flex; gap: 10px; margin-top: 30px; }" & vbCrLf & _
"button { " & vbCrLf & _
"  flex: 1; " & vbCrLf & _
"  padding: 12px; " & vbCrLf & _
"  border: none; " & vbCrLf & _
"  border-radius: 5px; " & vbCrLf & _
"  font-size: 14px; " & vbCrLf & _
"  font-weight: 600; " & vbCrLf & _
"  cursor: pointer; " & vbCrLf & _
"  transition: all 0.3s; " & vbCrLf & _
"}" & vbCrLf & _
".btn-start { background: #667eea; color: white; }" & vbCrLf & _
".btn-start:hover { background: #5568d3; transform: translateY(-2px); }" & vbCrLf & _
".btn-cancel { background: #ddd; color: #333; }" & vbCrLf & _
".btn-cancel:hover { background: #ccc; }" & vbCrLf & _
".contact { " & vbCrLf & _
"  text-align: center; " & vbCrLf & _
"  margin-top: 20px; " & vbCrLf & _
"  font-size: 11px; " & vbCrLf & _
"  color: #999; " & vbCrLf & _
"  border-top: 1px solid #eee; " & vbCrLf & _
"  padding-top: 15px; " & vbCrLf & _
"}" & vbCrLf & _
".contact a { color: #667eea; text-decoration: none; }" & vbCrLf & _
"</style>" & vbCrLf & _
"</head><body>" & vbCrLf & _
"<div class='container'>" & vbCrLf & _
"<div class='header'>" & vbCrLf & _
"<h1>PHARMACY MANAGEMENT SYSTEM</h1>" & vbCrLf & _
"<p>Server Configuration</p>" & vbCrLf & _
"<p style='font-size:11px; margin-top:10px;'>Developed by Mark T Crafts Tech Ltd</p>" & vbCrLf & _
"</div>" & vbCrLf & _
"<div class='form-group'>" & vbCrLf & _
"<label for='ip'>Server IP Address:</label>" & vbCrLf & _
"<input type='text' id='ip' value='" & strIP & "' required>" & vbCrLf & _
"</div>" & vbCrLf & _
"<div class='form-group'>" & vbCrLf & _
"<label for='port'>Server Port:</label>" & vbCrLf & _
"<input type='text' id='port' value='" & intPort & "' required>" & vbCrLf & _
"</div>" & vbCrLf & _
"<div class='button-group'>" & vbCrLf & _
"<button class='btn-start' onclick='doStart()'>Start Server</button>" & vbCrLf & _
"<button class='btn-cancel' onclick='doCancel()'>Cancel</button>" & vbCrLf & _
"</div>" & vbCrLf & _
"<div class='contact'>" & vbCrLf & _
"<p><strong>For Assistance:</strong></p>" & vbCrLf & _
"<p>Phone: 0780427684<br>Email: <a href='mailto:tumusiimemac@gmail.com'>tumusiimemac@gmail.com</a></p>" & vbCrLf & _
"</div>" & vbCrLf & _
"</div>" & vbCrLf & _
"<script>" & vbCrLf & _
"function doStart() {" & vbCrLf & _
"  var ip = document.getElementById('ip').value.trim();" & vbCrLf & _
"  var port = document.getElementById('port').value.trim();" & vbCrLf & _
"  if (!ip || !port) {" & vbCrLf & _
"    alert('Please enter both IP and Port');" & vbCrLf & _
"    return;" & vbCrLf & _
"  }" & vbCrLf & _
"  window.location.href = 'vbscript:' + parent.startServer(\"' + ip + '\", \"' + port + '\");';" & vbCrLf & _
"}" & vbCrLf & _
"function doCancel() {" & vbCrLf & _
"  window.close();" & vbCrLf & _
"}" & vbCrLf & _
"</script>" & vbCrLf & _
"</body></html>"

objIE.Navigate "about:blank"
While objIE.Busy Or objIE.ReadyState <> 4: WScript.Sleep 100: Wend

Set doc = objIE.Document
doc.Write strHTML
doc.Close

' Expose VBScript function to JavaScript
Dim win
Set win = objIE.Document.ParentWindow
' Since direct exposure doesn't work in modern IE, use a different approach
' We'll check for a flag file that the user clicks will create

' Wait for user to click Start
Dim bStartClicked
bStartClicked = False

Do While True
    ' Check if flag file exists (created by OnClick handler in a workaround)
    If objFSO.FileExists(strPharmacyDir & "\start-server.flag") Then
        ' Read flag
        Dim flagFile
        Set flagFile = objFSO.OpenTextFile(strPharmacyDir & "\start-server.flag", 1)
        Dim flagData
        flagData = flagFile.ReadAll()
        flagFile.Close()
        objFSO.DeleteFile strPharmacyDir & "\start-server.flag"
        
        ' Parse data
        Dim parts
        parts = Split(flagData, "|")
        If UBound(parts) >= 1 Then
            strIP = parts(0)
            intPort = parts(1)
        End If
        
        bStartClicked = True
        Exit Do
    End If
    
    ' Check if window closed
    On Error Resume Next
    If objIE Is Nothing Or Err <> 0 Then
        Exit Do
    End If
    On Error Goto 0
    
    WScript.Sleep 500
Loop

' Close IE
On Error Resume Next
objIE.Quit
On Error Goto 0

If bStartClicked Then
    ' Save config
    Dim cfgFile
    Set cfgFile = objFSO.CreateTextFile(configFile, True)
    cfgFile.WriteLine "IP=" & strIP
    cfgFile.WriteLine "PORT=" & intPort
    cfgFile.Close()
    
    ' Start server using command line
    Dim cmdLine
    cmdLine = "cmd /c cd /d """ & strPharmacyDir & """ && php -S " & strIP & ":" & intPort
    
    ' Execute in hidden mode and open browser
    objShell.Run cmdLine, 0, False
    WScript.Sleep 2000
    objShell.Run "http://" & strIP & ":" & intPort
End If
