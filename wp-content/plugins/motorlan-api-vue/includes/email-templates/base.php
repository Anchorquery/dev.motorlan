<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{subject}}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
        }
        .header {
            background: #005b96;
            color: #ffffff;
            padding: 10px;
            text-align: center;
        }
        .content {
            padding: 20px 0;
        }
        .footer {
            margin-top: 20px;
            font-size: 0.8em;
            text-align: center;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Motorlan</h1>
        </div>
        <div class="content">
            {{content}}
        </div>
        <div class="footer">
            <p>Este es un correo electrónico automático, por favor no respondas a este mensaje.</p>
            <p>&copy; <?php echo date('Y'); ?> Motorlan. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>