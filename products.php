<?php require_once __DIR__.'/../includes/studio.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    try {
        $id = positive($_POST['product_id'] ?? '');
        if (row('SELECT order_detail_id FROM order_details WHERE product_id=? LIMIT 1', [$id])) {
            throw new InputError('This SKU belongs to an existing order. Its history is protected; set stock to zero to stop sales.');
        }
        if (!stmt('DELETE FROM products WHERE product_id=?', [$id])->rowCount()) {
            throw new InputError('Product not found.');
        }
        $f = STORE_ROOT.'/uploads/product-'.$id.'.jpg';
        if (is_file($f)) {
            unlink($f);
        }
        flash('Product removed.');
    } catch (Throwable $e) {
        flash(admin_error($e), 'error');
    }
    go('admin/products.php');
}
$q = mb_substr(strval_input($_GET, 'q'), 0, 100);
$list = rows(PRODS.' WHERE p.product_name LIKE ? OR p.sku LIKE ? ORDER BY p.product_id', ['%'.$q.'%','%'.$q.'%']);
studio_head('Product wardrobe', 'products', 'Each row is one SKU with its own size, colour and stock.');?>
<div class="admin-toolbar"><form class="admin-filter"><input type="search" name="q" aria-label="Search SKU or product" value="<?=h($q)?>" placeholder="Search SKU or product"><button class="btn outline">Search</button></form><a class="btn dark" href="<?=u('admin/product-add.php')?>">+ Add product</a></div><div class="table-scroll"><table class="admin-table"><thead><tr><th>Piece</th><th>Category</th><th>Size / colour</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead><tbody><?php foreach ($list as $p):?><tr><td><div class="cell-piece"><img src="<?=h(image_for($p))?>" alt=""><div><?=h($p['product_name'])?><small><?=h($p['sku'])?></small></div></div></td><td><?=h($p['category_name'])?></td><td><?=h($p['size_label'].' / '.$p['color'])?></td><td class="no-wrap"><?=money($p['price'])?></td><td><?=$p['stock_qty']?></td><td><div class="row-actions"><a class="linked" href="<?=u('admin/product-edit.php?id='.$p['product_id'])?>">Edit</a><form method="post" data-confirm="Remove this SKU? Products with an order history cannot be deleted."><?=csrf()?><input type="hidden" name="product_id" value="<?=$p['product_id']?>"><button class="mini-link">Delete</button></form></div></td></tr><?php endforeach;
if (!$list) {
    echo '<tr><td colspan="6">No pieces found.</td></tr>';
}?></tbody></table></div><?php studio_foot();?>
