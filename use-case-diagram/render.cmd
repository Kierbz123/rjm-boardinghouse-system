@echo off
rem Usage: render.cmd 1900 1536   (width height from the SVG header) -> 2x PNG via headless Edge
"C:\Program Files (x86)\Microsoftdge\Application\msedge.exe" --headless --disable-gpu --hide-scrollbars --force-device-scale-factor=2 --window-size=%1,%2 --screenshot="%~dp0rjm-use-case-diagram.png" "file:///%~dp0rjm-use-case-diagram.svg"
