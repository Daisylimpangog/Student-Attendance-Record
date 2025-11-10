$dest = 'C:\xampp\htdocs\CHPCEBU-Attendance\assets\models'
if (-not (Test-Path $dest)) { New-Item -ItemType Directory -Path $dest | Out-Null }
$zip = Join-Path $env:TEMP 'faceapi.zip'
$repo = 'https://github.com/justadudewhohacks/face-api.js/archive/refs/heads/master.zip'
Write-Output "Downloading $repo to $zip ..."
Invoke-WebRequest -Uri $repo -OutFile $zip
$extract = Join-Path $env:TEMP 'faceapi_models'
if (Test-Path $extract) { Remove-Item -Recurse -Force $extract }
Expand-Archive -LiteralPath $zip -DestinationPath $extract -Force
$weights = Join-Path $extract 'face-api.js-master\weights'
if (Test-Path $weights) {
    Write-Output "Copying weights to $dest ..."
    Copy-Item -Path (Join-Path $weights '*') -Destination $dest -Recurse -Force
    Write-Output 'Done copying.'
} else {
    Write-Output 'Weights folder not found in archive.'
}
Remove-Item -Force $zip
Remove-Item -Recurse -Force $extract
Write-Output "Models downloaded and placed in $dest"
