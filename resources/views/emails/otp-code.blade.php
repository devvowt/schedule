<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Código de acesso</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e4e6eb;">
                    <tr>
                        <td style="background:#111827;padding:20px 28px;color:#ffffff;font-size:15px;font-weight:600;letter-spacing:.3px;">
                            {{ config('app.name') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 6px;font-size:18px;font-weight:600;color:#111827;">Seu código de acesso</p>
                            <p style="margin:0 0 22px;font-size:14px;line-height:1.6;color:#4b5563;">
                                Use o código abaixo para entrar no painel do agendador.
                            </p>

                            <div style="text-align:center;background:#f9fafb;border:1px dashed #d1d5db;border-radius:10px;padding:18px;margin-bottom:22px;">
                                <span style="font-family:'SFMono-Regular',Consolas,monospace;font-size:32px;font-weight:700;letter-spacing:8px;color:#111827;">{{ $code }}</span>
                            </div>

                            <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#6b7280;">
                                O código expira em <strong>{{ $ttlMinutes }} minutos</strong> e só pode ser usado uma vez.
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
                                Se não foi você quem solicitou, ignore este e-mail — nada será alterado.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;background:#f9fafb;border-top:1px solid #e4e6eb;font-size:12px;color:#9ca3af;">
                            Mensagem automática — não responda.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
