<!doctype html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Agendador') — {{ config('app.name') }}</title>
    <style>
        :root{
            --bg:#f4f5f7; --surface:#ffffff; --surface-2:#f9fafb; --border:#e5e7eb;
            --text:#111827; --muted:#6b7280; --brand:#111827; --brand-ink:#ffffff;
            --accent:#2563eb; --green:#047857; --green-bg:#ecfdf5; --red:#b91c1c; --red-bg:#fef2f2;
            --amber:#b45309; --amber-bg:#fffbeb; --blue:#1d4ed8; --blue-bg:#eff6ff;
            --gray:#4b5563; --gray-bg:#f3f4f6; --radius:10px;
            --mono:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;
        }
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--text);
             font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
             font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased}
        a{color:var(--accent);text-decoration:none}
        a:hover{text-decoration:underline}
        h1,h2,h3{margin:0;font-weight:650;letter-spacing:-.01em}
        code,kbd,pre{font-family:var(--mono)}

        /* ---- layout ---- */
        .shell{display:flex;min-height:100vh}
        .sidebar{width:232px;flex:0 0 232px;background:var(--brand);color:#e5e7eb;padding:20px 14px;display:flex;flex-direction:column;gap:18px}
        .sidebar .brand{display:flex;align-items:center;gap:9px;color:#fff;font-weight:650;font-size:15px;padding:0 8px}
        .sidebar .brand .dot{width:9px;height:9px;border-radius:50%;background:#34d399;box-shadow:0 0 0 3px rgba(52,211,153,.2)}
        .nav{display:flex;flex-direction:column;gap:2px}
        .nav a{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:8px;color:#cbd5e1;font-weight:500}
        .nav a:hover{background:rgba(255,255,255,.07);color:#fff;text-decoration:none}
        .nav a.active{background:rgba(255,255,255,.12);color:#fff}
        .sidebar .foot{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:14px;font-size:12px;color:#94a3b8}
        .sidebar .foot form{margin-top:8px}
        .main{flex:1;min-width:0;display:flex;flex-direction:column}
        .topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:16px 26px;display:flex;align-items:center;justify-content:space-between;gap:16px}
        .content{padding:24px 26px 48px;max-width:1180px;width:100%}

        /* ---- cartões ---- */
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
        .card + .card{margin-top:18px}
        .card-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px}
        .card-head h2{font-size:14px}
        .card-body{padding:18px}
        .grid{display:grid;gap:16px}
        .grid.cols-4{grid-template-columns:repeat(4,1fr)}
        .grid.cols-3{grid-template-columns:repeat(3,1fr)}
        .grid.cols-2{grid-template-columns:repeat(2,1fr)}
        @media(max-width:980px){.grid.cols-4{grid-template-columns:repeat(2,1fr)}.grid.cols-3,.grid.cols-2{grid-template-columns:1fr}}
        @media(max-width:760px){.shell{flex-direction:column}.sidebar{width:100%;flex:none}.grid.cols-4{grid-template-columns:1fr}}

        .stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px}
        .stat .label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600}
        .stat .value{font-size:26px;font-weight:680;margin-top:6px;letter-spacing:-.02em}
        .stat .hint{font-size:12px;color:var(--muted);margin-top:2px}

        /* ---- tabelas ---- */
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:13px}
        th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);
           padding:10px 14px;border-bottom:1px solid var(--border);background:var(--surface-2);white-space:nowrap}
        td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
        tr:last-child td{border-bottom:0}
        tbody tr:hover{background:var(--surface-2)}
        .empty{padding:34px 18px;text-align:center;color:var(--muted)}

        /* ---- badges ---- */
        .badge{display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:999px;font-size:11.5px;font-weight:600;white-space:nowrap}
        .badge.green{background:var(--green-bg);color:var(--green)}
        .badge.red{background:var(--red-bg);color:var(--red)}
        .badge.amber{background:var(--amber-bg);color:var(--amber)}
        .badge.blue{background:var(--blue-bg);color:var(--blue)}
        .badge.gray{background:var(--gray-bg);color:var(--gray)}
        .mono{font-family:var(--mono);font-size:12.5px}
        .muted{color:var(--muted)}
        .truncate{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:bottom}

        /* ---- botões ---- */
        .btn{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:8px;border:1px solid var(--border);
             background:var(--surface);color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
        .btn:hover{background:var(--surface-2);text-decoration:none}
        .btn.primary{background:var(--brand);border-color:var(--brand);color:var(--brand-ink)}
        .btn.primary:hover{opacity:.9;background:var(--brand)}
        .btn.danger{color:var(--red);border-color:#fecaca;background:var(--red-bg)}
        .btn.sm{padding:5px 10px;font-size:12px}
        .btn[disabled]{opacity:.5;cursor:not-allowed}
        .actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}

        /* ---- formulários ---- */
        .field{margin-bottom:16px}
        .field label{display:block;font-size:12.5px;font-weight:600;margin-bottom:5px}
        .field .help{font-size:12px;color:var(--muted);margin-top:5px;line-height:1.5}
        input[type=text],input[type=email],input[type=number],input[type=url],select,textarea{
            width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--surface);
            font-family:inherit;font-size:13.5px;color:var(--text)}
        textarea{min-height:88px;resize:vertical;font-family:var(--mono);font-size:12.5px}
        input:focus,select:focus,textarea:focus{outline:2px solid rgba(37,99,235,.35);outline-offset:-1px;border-color:var(--accent)}
        .check{display:flex;align-items:center;gap:8px;font-size:13.5px}
        .check input{width:16px;height:16px}
        .error{color:var(--red);font-size:12px;margin-top:5px}
        fieldset{border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;margin:0 0 18px}
        legend{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:0 6px}

        /* ---- alertas ---- */
        .alert{padding:11px 14px;border-radius:8px;font-size:13px;margin-bottom:18px;border:1px solid}
        .alert.success{background:var(--green-bg);border-color:#a7f3d0;color:var(--green)}
        .alert.error{background:var(--red-bg);border-color:#fecaca;color:var(--red)}
        .alert.info{background:var(--blue-bg);border-color:#bfdbfe;color:var(--blue)}
        .alert.warn{background:var(--amber-bg);border-color:#fde68a;color:var(--amber)}

        pre.output{background:#0f172a;color:#e2e8f0;padding:14px;border-radius:8px;overflow:auto;max-height:420px;font-size:12.5px;line-height:1.55;margin:0}
        .kv{display:grid;grid-template-columns:180px 1fr;gap:9px 16px;font-size:13px}
        .kv dt{color:var(--muted);font-weight:600}
        .kv dd{margin:0;min-width:0;overflow-wrap:anywhere}
        .pagination{display:flex;gap:6px;padding:14px 18px;flex-wrap:wrap;align-items:center;font-size:13px}
        .pagination svg{width:14px;height:14px}
        .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
        .filters .field{margin:0;min-width:150px}
    </style>
    @stack('head')
</head>
<body>
@yield('body')
</body>
</html>
