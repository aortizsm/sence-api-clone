<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SENCE - Redireccionando...</title>
</head>
<body>
    <form id="sence_form" action="<?= esc($url) ?>" method="POST">
        <?php foreach ($data as $key => $value): ?>
        <input type="hidden" name="<?= esc($key) ?>" value="<?= esc((string)$value) ?>">
        <?php endforeach; ?>
    </form>
    <script>document.getElementById("sence_form").submit();</script>
</body>
</html>
