param(
    [string] $BaseUrl = "http://127.0.0.1:8000/api",
    [string] $Email = "admin@acreditacion.local",
    [string] $Password = "password"
)

$ErrorActionPreference = "Stop"

$login = Invoke-RestMethod `
    -Uri "$BaseUrl/auth/login" `
    -Method Post `
    -ContentType "application/json" `
    -Body (@{ email = $Email; password = $Password } | ConvertTo-Json)

$headers = @{
    Authorization = "Bearer $($login.token)"
    Accept = "application/json"
}

$teacher = (Invoke-RestMethod -Uri "$BaseUrl/admin/teachers" -Headers $headers)[0]
$program = (Invoke-RestMethod -Uri "$BaseUrl/admin/programs" -Headers $headers)[0]
$cycle = (Invoke-RestMethod -Uri "$BaseUrl/accreditation-cycles" -Headers $headers)[0]

$sample = Join-Path (Get-Location) "backend\storage\app\teacher-cv-test.pdf"
$pdf = "%PDF-1.4`n1 0 obj`n<< /Type /Catalog >>`nendobj`ntrailer`n<<>>`n%%EOF"
[System.IO.File]::WriteAllBytes($sample, [System.Text.Encoding]::ASCII.GetBytes($pdf))

$raw = & curl.exe -sS -X POST "$BaseUrl/admin/teachers/$($teacher.id)/cv" `
    -H "Authorization: Bearer $($login.token)" `
    -H "Accept: application/json" `
    -F "program_id=$($program.id)" `
    -F "accreditation_cycle_id=$($cycle.id)" `
    -F "title=CV docente prueba C6" `
    -F "description=Prueba de carga CV docente" `
    -F "file=@$sample;type=application/pdf"

$created = $raw | ConvertFrom-Json

if ($null -eq $created.data.id) {
    throw "No se pudo crear evidencia CV: $raw"
}

[pscustomobject]@{
    teacher_id = $teacher.id
    evidence_id = $created.data.id
    status = $created.data.status
    requirement = $created.data.requirement.code
    criterion = $created.data.criterion.code
    teacher_email = $created.data.teacher.email
    message = $created.message
} | ConvertTo-Json
