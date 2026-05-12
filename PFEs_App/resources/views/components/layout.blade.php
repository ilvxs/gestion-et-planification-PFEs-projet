<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        {{ $title ?? 'Gestion PFEs' }}
    </title>

    <style>

        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background-color: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        form {
            margin-bottom: 30px;
        }

        input,
        button,
        textarea {
            padding: 10px;
            margin-top: 10px;
        }

        button {
            cursor: pointer;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }

    </style>

</head>

<body>

    <div class="container">

        {{ $slot }}

    </div>

</body>

</html>