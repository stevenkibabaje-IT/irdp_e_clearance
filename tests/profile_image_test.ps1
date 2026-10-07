$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$testDirectory = Join-Path $PSScriptRoot ('profile-image-' + [Guid]::NewGuid().ToString('N'))
[void][IO.Directory]::CreateDirectory($testDirectory)
try {
    foreach ($case in @(
        @{Width=960; Height=1280; Extension='png'},
        @{Width=1280; Height=960; Extension='jpg'},
        @{Width=96; Height=128; Extension='png'}
    )) {
        $source = Join-Path $testDirectory ('source.' + $case.Extension)
        $destination = Join-Path $testDirectory 'result.jpg'
        $fixture = [System.Drawing.Bitmap]::new($case.Width, $case.Height)
        $graphics = [System.Drawing.Graphics]::FromImage($fixture)
        try {
            $graphics.Clear([System.Drawing.Color]::Red)
            $graphics.FillRectangle([System.Drawing.Brushes]::Blue, 0, 0, [int]($case.Width / 2), $case.Height)
            $format = if ($case.Extension -eq 'png') { [System.Drawing.Imaging.ImageFormat]::Png } else { [System.Drawing.Imaging.ImageFormat]::Jpeg }
            $fixture.Save($source, $format)
        } finally { $graphics.Dispose(); $fixture.Dispose() }
        & (Join-Path $PSScriptRoot '../includes/resize_profile.ps1') -Source $source -Destination $destination
        $result = [System.Drawing.Bitmap]::new($destination)
        try {
            if ($result.Width -ne 256 -or $result.Height -ne 256) { throw 'Unexpected output dimensions.' }
            $left = $result.GetPixel(110,128)
            $right = $result.GetPixel(146,128)
            if ($left.B -lt 200 -or $left.R -gt 40 -or $right.R -lt 200 -or $right.B -gt 40) {
                throw "Resizing lost the portrait content for $($case.Width)x$($case.Height) $($case.Extension)."
            }
        } finally { $result.Dispose() }
        Write-Output "PASS: preserved image colours for $($case.Width)x$($case.Height) $($case.Extension)"
    }
} finally {
    # Remove only the files in the unique fixture directory created above.
    foreach ($file in [IO.Directory]::GetFiles($testDirectory)) { [IO.File]::Delete($file) }
    [IO.Directory]::Delete($testDirectory)
}
