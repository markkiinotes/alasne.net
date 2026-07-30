<section class="page-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Review platform permissions used by roles, routes, and engines.</p>
</section>

<div class="panel">
    <div class="table-header">
        <h2>Platform Permissions</h2>

        <a href="#" class="button-muted">Create Permission</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($permissions as $permission): ?>
                <tr>
                    <td><?= htmlspecialchars($permission['name']) ?></td>
                    <td><?= htmlspecialchars($permission['slug']) ?></td>
                    <td><?= htmlspecialchars($permission['description'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>