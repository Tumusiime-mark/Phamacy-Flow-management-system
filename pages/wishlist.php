<?php
$branchId = has_global_branch_access() ? (isset($_GET['branch_id']) && trim((string) $_GET['branch_id']) !== '' ? (int) $_GET['branch_id'] : null) : current_branch_id();
$items = wishlist_items($branchId);
$branches = visible_branches_for_user();
?>
<section class="card report-filter-card">
    <div class="section-head">
        <h3>Wishlist</h3>
        <span>Out-of-stock demand and replenishment priority</span>
    </div>
    <form method="get" class="form-grid">
        <input type="hidden" name="page" value="wishlist">
        <?php if (has_global_branch_access()): ?>
        <label>
            <span>Branch</span>
            <select name="branch_id">
                <option value="">All branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?= (int) $branch['id'] ?>" <?= $branchId === (int) $branch['id'] ? 'selected' : '' ?>><?= h($branch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <div class="form-actions full-width">
            <button class="btn-primary" type="submit">Apply</button>
            <button class="btn-secondary" type="button" onclick="window.print()">Print Wishlist</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="section-head">
        <h3>Wishlist Register</h3>
        <span><?= count($items) ?> open items</span>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Branch</th>
                    <th>Product</th>
                    <th>Stock</th>
                    <th>Requests</th>
                    <th>Priority</th>
                    <th>Requested By</th>
                    <th>Note</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= h($item['branch_name']) ?></td>
                    <td><?= h($item['product_name']) ?></td>
                    <td><?= (int) ($item['current_stock'] ?? 0) ?></td>
                    <td><?= (int) ($item['request_count'] ?? 1) ?></td>
                    <td><?= h($item['priority']) ?></td>
                    <td><?= h($item['requested_by_name']) ?></td>
                    <td><?= h($item['note']) ?></td>
                    <td><?= h($item['updated_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($items === []): ?>
                <tr><td colspan="8">No open wishlist items.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
