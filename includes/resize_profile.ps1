param([Parameter(Mandatory=$true)][string]$Source,[Parameter(Mandatory=$true)][string]$Destination)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$image = $null
$bitmap = $null
$graphics = $null
try {
    $image = [System.Drawing.Image]::FromFile($Source)
    if ($image.Width -gt 6000 -or $image.Height -gt 6000 -or ([long]$image.Width * $image.Height) -gt 16000000) { throw 'Image dimensions exceed the limit.' }
    # Keep both arguments floating point: an integer 1 selects the integer Min
    # overload and rounds a fractional scale down to zero for large portraits.
    $scale = [Math]::Min([double]1.0, [double]([Math]::Min(256.0 / $image.Width, 256.0 / $image.Height)))
    $width = [Math]::Max(1, [int][Math]::Round($image.Width * $scale))
    $height = [Math]::Max(1, [int][Math]::Round($image.Height * $scale))
    $bitmap = New-Object System.Drawing.Bitmap 256,256
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.Clear([System.Drawing.Color]::White)
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    # Make destination geometry and source pixel bounds explicit.
    $destinationRect = [System.Drawing.Rectangle]::new([int]((256 - $width) / 2), [int]((256 - $height) / 2), $width, $height)
    $graphics.DrawImage($image, $destinationRect, [single]0, [single]0, [single]$image.Width, [single]$image.Height, [System.Drawing.GraphicsUnit]::Pixel)
    $graphics.Flush()
    $bitmap.Save($Destination, [System.Drawing.Imaging.ImageFormat]::Jpeg)
} finally {
    if ($graphics) { $graphics.Dispose() }
    if ($bitmap) { $bitmap.Dispose() }
    if ($image) { $image.Dispose() }
}
