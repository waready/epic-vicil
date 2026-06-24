param(
    [string] $BaseUrl = "http://127.0.0.1:8000/api",
    [string] $Email = "admin@acreditacion.local",
    [string] $Password = "password"
)

$ErrorActionPreference = "Stop"

function Invoke-JsonPost($Uri, $Headers, $Body) {
    Invoke-RestMethod `
        -Uri $Uri `
        -Headers $Headers `
        -Method Post `
        -ContentType "application/json" `
        -Body ($Body | ConvertTo-Json -Depth 8)
}

function First-Item($Value, $Name) {
    if ($null -eq $Value) {
        throw "No se encontro $Name."
    }

    if ($Value -is [array]) {
        if ($Value.Count -lt 1) {
            throw "No se encontro $Name."
        }

        return $Value[0]
    }

    return $Value
}

$health = Invoke-RestMethod -Uri "$BaseUrl/health" -Method Get
$login = Invoke-JsonPost "$BaseUrl/auth/login" @{} @{ email = $Email; password = $Password }
$headers = @{
    Authorization = "Bearer $($login.token)"
    Accept = "application/json"
}

$me = Invoke-RestMethod -Uri "$BaseUrl/auth/me" -Headers $headers
$program = First-Item (Invoke-RestMethod -Uri "$BaseUrl/programs" -Headers $headers) "programa"
$cycle = First-Item (Invoke-RestMethod -Uri "$BaseUrl/accreditation-cycles" -Headers $headers) "ciclo"
$criteria = Invoke-RestMethod -Uri "$BaseUrl/accreditation-criteria" -Headers $headers
$criterion = First-Item ($criteria | Where-Object { $_.accreditation_model_id -eq $cycle.accreditation_model_id }) "criterio"
$requirement = First-Item (Invoke-RestMethod -Uri "$BaseUrl/evidence-requirements?criterion_id=$($criterion.id)" -Headers $headers) "requerimiento"
$tasksUrl = "$BaseUrl/evidence-tasks/catalog?program_id=$($program.id)&cycle_id=$($cycle.id)&criterion_id=$($criterion.id)&evidence_requirement_id=$($requirement.id)"
$task = First-Item (Invoke-RestMethod -Uri $tasksUrl -Headers $headers) "tarea"

$sample = Join-Path (Get-Location) "backend\storage\app\test-evidence.pdf"
$sampleV2 = Join-Path (Get-Location) "backend\storage\app\test-evidence-v2.pdf"
$pdfV1 = "%PDF-1.4`n1 0 obj`n<< /Type /Catalog >>`nendobj`ntrailer`n<<>>`n%%EOF"
$pdfV2 = "%PDF-1.4`n1 0 obj`n<< /Type /Catalog /Version 2 >>`nendobj`ntrailer`n<<>>`n%%EOF"
[System.IO.File]::WriteAllBytes($sample, [System.Text.Encoding]::ASCII.GetBytes($pdfV1))
[System.IO.File]::WriteAllBytes($sampleV2, [System.Text.Encoding]::ASCII.GetBytes($pdfV2))

$createRaw = & curl.exe -sS -X POST "$BaseUrl/evidences" `
    -H "Authorization: Bearer $($login.token)" `
    -H "Accept: application/json" `
    -F "program_id=$($program.id)" `
    -F "accreditation_cycle_id=$($cycle.id)" `
    -F "criterion_id=$($criterion.id)" `
    -F "evidence_requirement_id=$($requirement.id)" `
    -F "evidence_task_id=$($task.id)" `
    -F "title=Prueba tecnica de portafolio ICACIT" `
    -F "description=Evidencia generada durante prueba integral del MVP." `
    -F "file=@$sample;type=application/pdf"

$created = $createRaw | ConvertFrom-Json
if ($null -eq $created.data.id) {
    throw "No se pudo crear evidencia: $createRaw"
}

$evidenceId = $created.data.id
$versionRaw = & curl.exe -sS -X POST "$BaseUrl/evidences/$evidenceId/versions" `
    -H "Authorization: Bearer $($login.token)" `
    -H "Accept: application/json" `
    -F "change_summary=Version corregida para prueba de flujo." `
    -F "file=@$sampleV2;type=application/pdf"

$versioned = $versionRaw | ConvertFrom-Json
$observed = Invoke-JsonPost "$BaseUrl/evidences/$evidenceId/observe" $headers @{ comment = "Observacion de prueba." }
$validated = Invoke-JsonPost "$BaseUrl/evidences/$evidenceId/validate" $headers @{ comment = "Validacion de prueba." }
$approved = Invoke-JsonPost "$BaseUrl/evidences/$evidenceId/approve" $headers @{ comment = "Aprobacion de prueba." }
$export = Invoke-JsonPost "$BaseUrl/exports/evidences-zip" $headers @{
    accreditation_cycle_id = $cycle.id
    program_id = $program.id
    statuses = @("approved")
}

[pscustomobject]@{
    health = $health.status
    user = $me.email
    program = $program.code
    cycle = $cycle.year
    criterion = $criterion.code
    evidence_id = $evidenceId
    created_status = $created.data.status
    version_status = $versioned.data.status
    version_number = $versioned.data.version_number
    observed_status = $observed.data.status
    validated_status = $validated.data.status
    approved_status = $approved.data.status
    export_status = $export.data.status
    export_path = $export.data.path
    export_files = $export.data.stats.total_files
} | ConvertTo-Json
