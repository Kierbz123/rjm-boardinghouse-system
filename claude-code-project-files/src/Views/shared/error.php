<?php /** Rendered by App\Support\ErrorPage::render() — standalone, no nav (works even when the session or DB is the problem). */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($title) ?> — RJM Boardinghouse</title>
    <link rel="stylesheet" href="/assets/css/app.css" />
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-900 grid place-items-center p-5">
    <main class="w-full max-w-lg text-center">
        <p class="text-[7rem] leading-none font-semibold tracking-tight text-neutral-200 select-none" aria-hidden="true"><?= (int) http_response_code() ?></p>
        <h1 class="mt-2 text-2xl font-semibold"><?= htmlspecialchars($title) ?></h1>
        <p class="mt-3 text-neutral-500 leading-relaxed"><?= htmlspecialchars($body) ?></p>
        <p class="mt-8"><a class="btn btn-primary" href="<?= htmlspecialchars($home) ?>"><?= htmlspecialchars($homeLabel) ?></a></p>
    </main>
</body>
</html>
