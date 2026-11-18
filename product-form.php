<?php
require_once __DIR__.'/studio.php';
function staged_image(): ?string
{
    $up = $_FILES['photo'] ?? null;
    if (!$up || ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($up['error'] !== UPLOAD_ERR_OK || $up['size'] > 4 * 1024 * 1024 || !is_uploaded_file($up['tmp_name'])) {
        throw new InputError('Choose a JPG, PNG or WebP of up to 4 MB.');
    }
    if (!extension_loaded('gd')) {
        throw new InputError('Enable the PHP GD extension to upload images.');
    }
    $info = @getimagesize($up['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 12000000) {
        throw new InputError('Choose a valid JPG, PNG or WebP image under 12 megapixels.');
    }
    $im = @imagecreatefromstring(file_get_contents($up['tmp_name']));
    if (!$im) {
        throw new InputError('This photo could not be decoded.');
    }
    $scale = min(1, 1400 / max($info[0], $info[1]));
    $w = max(1, (int)round($info[0] * $scale));
    $h = max(1, (int)round($info[1] * $scale));
    $canvas = imagecreatetruecolor($w, $h);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 246, 242, 235));
    imagecopyresampled($canvas, $im, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
    $file = tempnam(private_dir(), 'photo-');
    if (!$file || !imagejpeg($canvas, $file, 88)) {
        throw new RuntimeException('Image could not be saved');
    }
    return $file;
}
function product_editor(?int $id): void
{
    $p = $id ? row(PRODS.' WHERE p.product_id=?', [$id]) : ['product_name' => '','sku' => '','size_label' => '','color' => '','price' => '','stock_qty' => 0,'category_id' => 0];
    if (!$p) {
        fail_page(404, 'Product not found.');
    }
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_post();
        $tmp = null;
        try {
            $cat = positive($_POST['category_id'] ?? '');
            if (!row('SELECT category_id FROM categories WHERE category_id=?', [$cat])) {
                throw new InputError('Choose an existing category.');
            }
            $v = [$cat,field('sku', 50),field('product_name', 160),field('size_label', 20),field('color', 60),amount_field('price', true),positive($_POST['stock_qty'] ?? '', 4294967295, 0)];
            $tmp = staged_image();
            $db = db();
            $db->beginTransaction();
            if ($id) {
                $locked = row('SELECT stock_qty FROM products WHERE product_id=? FOR UPDATE', [$id]);
                if (!$locked || (int)$locked['stock_qty'] !== positive($_POST['original_stock'] ?? '', 4294967295, 0)) {
                    throw new InputError('Stock changed while you were editing. Reload this page before saving.');
                }
                stmt('UPDATE products SET category_id=?,sku=?,product_name=?,size_label=?,color=?,price=?,stock_qty=? WHERE product_id=?', array_merge($v, [$id]));
                $target = $id;
            } else {
                stmt('INSERT INTO products (category_id,sku,product_name,size_label,color,price,stock_qty) VALUES (?,?,?,?,?,?,?)', $v);
                $target = (int)$db->lastInsertId();
            }
            $file = STORE_ROOT.'/uploads/product-'.$target.'.jpg';
            if ($tmp && !rename($tmp, $file)) {
                throw new RuntimeException('Image write failed');
            }
            if (!$tmp && isset($_POST['reset_photo']) && is_file($file)) {
                unlink($file);
            }
            $db->commit();
            flash('Product saved.');
            go('admin/products.php');
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            if ($tmp && is_file($tmp)) {
                unlink($tmp);
            }
            $err = admin_error($e);
        }
    }
    studio_head($id ? 'Edit piece' : 'Add a piece', 'products', 'Images live in local files, separate from the database.');
    if ($err) {
        echo '<p class="error-box" role="alert">'.h($err).'</p>';
    }?><form class="admin-form" method="post" enctype="multipart/form-data"><?=csrf()?><input type="hidden" name="original_stock" value="<?=h($_POST['original_stock'] ?? $p['stock_qty'])?>"><div class="field-grid"><?php foreach (['product_name' => ['Product name',160],'sku' => ['SKU',50],'size_label' => ['Size',20],'color' => ['Colour',60],'price' => ['Unit price (LAK)',16],'stock_qty' => ['Stock quantity',10]] as $k => [$name,$max]) {
        input_box($name, $k, strval_input($_POST, $k, (string)$p[$k]), 'text', true, $max);
    }?><label class="field">Category<select name="category_id" required><option value="">Choose a category</option><?php foreach (cats() as $c):?><option value="<?=$c['category_id']?>" <?=($_POST['category_id'] ?? $p['category_id']) == $c['category_id'] ? 'selected' : ''?>><?=h($c['category_name'])?></option><?php endforeach;?></select></label><label class="field">Local photo (optional)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label></div><?php if ($id):?><div class="edit-photo"><img src="<?=h(image_for($p))?>" alt="Current product photo"><label class="checkline"><input type="checkbox" name="reset_photo">Restore default SKU image</label></div><?php endif;?><p class="small muted">JPG, PNG or WebP · up to 4 MB / 12 MP. Photos are decoded and stored as JPEG.</p><div class="row-actions"><button class="btn dark">Save piece</button><a class="btn outline" href="<?=u('admin/products.php')?>">Cancel</a></div></form><?php studio_foot();
}
