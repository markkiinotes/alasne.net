<style>
.supplier-form-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
@media(max-width:800px) {
    .supplier-form-grid { grid-template-columns:1fr; }
}
</style>

<section class="page-header">
    <h1>Edit Supplier</h1>
    <p>
        <?= htmlspecialchars($supplier['name']) ?>
    </p>
</section>

<?php if ($error): ?>
    <div class="alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<section class="panel form-panel">
    <form
        method="POST"
        action="/admin/suppliers/<?= htmlspecialchars(
            (string) $supplier['id']
        ) ?>"
    >
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= htmlspecialchars(
                $csrf_token
            ) ?>"
        >

        <?php require __DIR__ . '/_form.php'; ?>

        <div class="form-actions">
            <button
                type="submit"
                class="button-primary"
            >
                Save Supplier
            </button>
            <a
                href="/admin/suppliers/<?= htmlspecialchars(
                    (string) $supplier['id']
                ) ?>"
                class="button-muted"
            >
                Cancel
            </a>
        </div>
    </form>
</section>
