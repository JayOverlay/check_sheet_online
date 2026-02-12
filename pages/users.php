<?php
require_once '../config/database.php';
include '../includes/header.php';

// Pagination Setup
$items_per_page = 15;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $items_per_page;

$users = [];
$total_items = 0;

try {
    // Get total count
    $total_items = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // Fetch users
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">User Management</h4>
    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-user-plus me-2"></i> Add New User
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i> User saved successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <strong>Error:</strong>
        <?php echo isset($_GET['details']) ? htmlspecialchars($_GET['details']) : 'Failed to save user data.'; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card card-premium">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-user-friends fa-3x opacity-25 mb-3"></i>
                                <p>No users found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr class="align-middle">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php
                                        $u_initial = strtoupper(substr($u['full_name'], 0, 1));
                                        $u_colors = ['primary', 'success', 'danger', 'warning', 'info', 'dark'];
                                        $u_bg = $u_colors[ord($u_initial) % count($u_colors)];
                                        ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-<?php echo $u_bg; ?> text-white me-3"
                                            style="width: 40px; height: 40px; font-size: 1.2rem; font-weight: bold;">
                                            <?php echo $u_initial; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold">
                                                <?php echo $u['full_name']; ?>
                                            </div>
                                            <div class="text-muted small">
                                                <span class="me-2"><i
                                                        class="fas fa-id-card-alt me-1"></i><?php echo $u['username']; ?></span>
                                                <?php if ($u['email']): ?>
                                                    <span><i class="fas fa-envelope me-1"></i><?php echo $u['email']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $roleClass = 'bg-secondary';
                                    if ($u['role'] == 'admin')
                                        $roleClass = 'bg-danger';
                                    elseif ($u['role'] == 'leader')
                                        $roleClass = 'bg-primary';
                                    elseif ($u['role'] == 'Technicien')
                                        $roleClass = 'bg-info';
                                    ?>
                                    <span
                                        class="badge <?php echo $roleClass; ?>-subtle text-<?php echo str_replace('-subtle', '', $roleClass); ?> border border-<?php echo str_replace('-subtle', '', $roleClass); ?>-subtle px-3 text-uppercase">
                                        <?php echo $u['role']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $u['department'] ?: '---'; ?>
                                </td>
                                <td>
                                    <span
                                        class="badge bg-<?php echo $u['status'] == 'Active' ? 'success' : 'secondary'; ?>-subtle text-<?php echo $u['status'] == 'Active' ? 'success' : 'secondary'; ?> px-2">
                                        <?php echo $u['status']; ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?php echo date('d/m/Y', strtotime($u['created_at'])); ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-light rounded-circle me-1"
                                        onclick='editUser(<?php echo json_encode($u); ?>)'>
                                        <i class="fas fa-edit text-primary"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light rounded-circle text-danger"
                                        onclick="confirmDelete('../actions/delete_user.php?id=<?php echo $u['id']; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination pagination-sm justify-content-end mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link shadow-none" href="?page=<?php echo $page - 1; ?>"><i
                                class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link shadow-none" href="?page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link shadow-none" href="?page=<?php echo $page + 1; ?>"><i
                                class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="../actions/save_user.php" method="POST">
                <input type="hidden" name="user_id" id="user_id">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0" id="modalTitle">Register New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">EN Number</label>
                        <input type="text" class="form-control" name="username" id="username" required
                            placeholder="Employee Number (e.g. EN12345)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Email Address</label>
                        <input type="email" class="form-control" name="email" id="email" placeholder="user@hana.co.th">
                    </div>
                    <div class="mb-3" id="passwordField">
                        <label class="form-label small fw-bold text-uppercase">Password</label>
                        <input type="password" class="form-control" name="password" id="password" required
                            placeholder="Min 6 characters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Full Name</label>
                        <input type="text" class="form-control" name="full_name" id="full_name" required
                            placeholder="Firstname Lastname">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Role</label>
                            <select class="form-select" name="role" id="role" required>
                                <option value="user">User (Operator)</option>
                                <option value="leader">Leader</option>
                                <option value="Technicien">Technicien</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Department</label>
                        <input type="text" class="form-control" name="department" id="department"
                            placeholder="Production / QA / Maintenance">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editUser(u) {
        document.getElementById('modalTitle').innerText = 'Edit User';
        document.getElementById('user_id').value = u.id;
        document.getElementById('username').value = u.username;
        document.getElementById('full_name').value = u.full_name;
        document.getElementById('email').value = u.email || '';
        document.getElementById('role').value = u.role;
        document.getElementById('status').value = u.status;
        document.getElementById('department').value = u.department;

        // Hide password field when editing for simplicity, can implement change password later
        document.getElementById('password').required = false;
        document.getElementById('passwordField').style.opacity = '0.5';

        var modal = new bootstrap.Modal(document.getElementById('addUserModal'));
        modal.show();
    }

    // Reset modal when closing
    document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalTitle').innerText = 'Register New User';
        document.getElementById('user_id').value = '';
        document.getElementById('username').value = '';
        document.getElementById('full_name').value = '';
        document.getElementById('email').value = '';
        document.getElementById('role').value = 'user';
        document.getElementById('status').value = 'Active';
        document.getElementById('department').value = '';
        document.getElementById('password').required = true;
        document.getElementById('passwordField').style.opacity = '1';
    });
</script>

<?php include '../includes/footer.php'; ?>