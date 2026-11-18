<?php require_once __DIR__.'/../includes/studio.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    try {
        $act = choice('action', ['add','edit','delete']);
        if ($act !== 'add') {
            $id = positive($_POST['category_id'] ?? '');
        }
        if ($act === 'delete') {
            if (scalar('SELECT COUNT(*) FROM products WHERE category_id=?', [$id])) {
                throw new InputError('Move all products to another category before deleting this one.');
            }
            stmt('DELETE FROM categories WHERE category_id=?', [$id]);
        } else {
            $v = [field('category_name', 100),field('description', 255, false) ?: null];
            if ($act === 'add') {
                stmt('INSERT INTO categories (category_name,description) VALUES (?,?)', $v);
            } else {
                stmt('UPDATE categories SET category_name=?,description=? WHERE category_id=?', array_merge($v, [$id]));
            }
        }
        flash($act === 'delete' ? 'Category removed.' : 'Category saved.');
    } catch (Throwable $e) {
        flash(admin_error($e), 'error');
    }
    go('admin/categories.php');
}
studio_head('Categories', 'categories', 'Manage the groups that appear in the storefront.');?>
<form class="admin-form" method="post"><?=csrf()?><input type="hidden" name="action" value="add"><h2>Add category</h2><div class="field-grid"><?php input_box('Category name', 'category_name', '', 'text', true, 100);
input_box('Description (optional)', 'description', '', 'text', false, 255);?></div><button class="btn dark">Add category</button></form><div class="category-admin"><?php foreach (cats() as $c):?><article><p class="eyebrow">CATEGORY #<?=$c['category_id']?></p><form method="post"><?=csrf()?><input type="hidden" name="action" value="edit"><input type="hidden" name="category_id" value="<?=$c['category_id']?>"><?php input_box('Name', 'category_name', $c['category_name'], 'text', true, 100);
    input_box('Description', 'description', $c['description'] ?? '', 'text', false, 255);?><button class="btn outline">Save changes</button></form><form method="post" data-confirm="Delete this category? Linked products are protected."><?=csrf()?><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?=$c['category_id']?>"><button class="mini-link">Delete category</button></form></article><?php endforeach;?></div><?php studio_foot();?>
