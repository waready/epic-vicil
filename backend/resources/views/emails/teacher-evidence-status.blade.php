<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de evidencias ICACIT</title>
</head>
<body style="margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#102a43;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="720" cellspacing="0" cellpadding="0" style="max-width:720px;width:94%;background:#ffffff;border:1px solid #d9e2ec;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#0f4c81;color:#ffffff;padding:20px 24px;">
                            <h1 style="margin:0;font-size:22px;">Estado de cumplimiento de evidencias</h1>
                            <p style="margin:6px 0 0;font-size:14px;">{{ $summary['cycle'] }} - {{ $summary['program'] }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 24px;">
                            <p style="margin:0 0 14px;">Estimado(a) <strong>{{ $summary['teacher'] }}</strong>,</p>
                            <p style="margin:0 0 18px;">Este es el resumen de evidencias asignadas en el sistema de acreditacion.</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:20px;">
                                <tr>
                                    <td style="padding:12px;background:#eef6fc;border:1px solid #d9e2ec;"><strong>Asignadas</strong><br>{{ $summary['total'] }}</td>
                                    <td style="padding:12px;background:#eef6fc;border:1px solid #d9e2ec;"><strong>Enviadas</strong><br>{{ $summary['submitted'] }}</td>
                                    <td style="padding:12px;background:#fff4e5;border:1px solid #f8d9a2;"><strong>Faltantes</strong><br>{{ $summary['missing'] }}</td>
                                    <td style="padding:12px;background:#eefcf3;border:1px solid #c7ead3;"><strong>Avance</strong><br>{{ $summary['progress'] }}%</td>
                                </tr>
                            </table>

                            @if(count($missingTasks))
                                <h2 style="font-size:17px;margin:0 0 10px;">Evidencias faltantes</h2>
                                <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:13px;">
                                    <thead>
                                        <tr>
                                            <th align="left" style="padding:9px;border-bottom:2px solid #0f4c81;">Curso / contexto</th>
                                            <th align="left" style="padding:9px;border-bottom:2px solid #0f4c81;">Evidencia requerida</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($missingTasks as $task)
                                            <tr>
                                                <td style="padding:9px;border-bottom:1px solid #e5edf5;vertical-align:top;">{{ $task['context'] }}</td>
                                                <td style="padding:9px;border-bottom:1px solid #e5edf5;vertical-align:top;">
                                                    <strong>{{ $task['code'] }}</strong><br>{{ $task['name'] }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p style="padding:12px;background:#eefcf3;border:1px solid #c7ead3;border-radius:6px;">No tienes evidencias faltantes registradas.</p>
                            @endif

                            <p style="margin:20px 0 0;color:#627d98;font-size:12px;">Mensaje automatico del Sistema de Acreditacion ICACIT - UNAP Puno.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
