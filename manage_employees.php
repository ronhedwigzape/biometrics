<?php
include 'db.php';

function flashMessage($type, $title, $message) {
    $_SESSION['message'] = '<div class="app-flash app-flash-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '" role="status">'
        . '<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>'
        . '<span>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</span>'
        . '<button type="button" class="app-flash-close" aria-label="Dismiss">&times;</button>'
        . '</div>';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'assign':
                $employee_id = $mysqli->real_escape_string($_POST['employee_id']);
                $employee_name = $mysqli->real_escape_string($_POST['employee_name']);
                
                $sql = "INSERT INTO employees (employee_id, employee_name) VALUES ('$employee_id', '$employee_name')";
                if ($mysqli->query($sql)) {
                    flashMessage('success', 'Employee assigned', 'Employee assigned successfully.');
                } else {
                    flashMessage('danger', 'Assign failed', 'Error assigning employee.');
                }
                break;

            case 'update':
                $employee_id = $mysqli->real_escape_string($_POST['employee_id']);
                $employee_name = $mysqli->real_escape_string($_POST['new_employee_name']);
                
                $sql = "UPDATE employees SET employee_name = '$employee_name' WHERE employee_id = '$employee_id'";
                if ($mysqli->query($sql)) {
                    flashMessage('success', 'Employee updated', 'Employee updated successfully.');
                } else {
                    flashMessage('danger', 'Update failed', 'Error updating employee.');
                }
                break;

            case 'unassign':
                $employee_id = $mysqli->real_escape_string($_POST['employee_id']);
                
                $sql = "DELETE FROM employees WHERE employee_id = '$employee_id'";
                if ($mysqli->query($sql)) {
                    flashMessage('success', 'Employee unassigned', 'Employee unassigned successfully.');
                } else {
                    flashMessage('danger', 'Unassign failed', 'Error unassigning employee.');
                }
                break;
        }
        header("Location: manage_employees.php");
        exit();
    }
}

// Fetch all employees
$query = "SELECT * FROM employees ORDER BY employee_name";
$result = $mysqli->query($query);

include 'header.php';
?>

<section class="app-panel employee-panel">
    <?php
    if(isset($_SESSION['message'])) {
        echo $_SESSION['message'];
        unset($_SESSION['message']);
    }
    ?>

    <div class="section-heading">
        <div>
            <span class="section-kicker">Employee Directory</span>
            <h2>Manage Employees</h2>
        </div>
        <div>
            <button class="btn btn-primary btn-action" type="button" id="toggleAssignForm">
                Add New Employee
            </button>
            <a href="index.php" class="btn btn-secondary">Back to Logs</a>
        </div>
    </div>

    <!-- Assign New Employee Form -->
    <div class="collapse mb-4" id="assignEmployeeForm">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Assign New Employee</h4>
                <button type="button" class="app-card-close" aria-label="Close" id="closeAssignForm">&times;</button>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="assign">
                    <div class="col-md-5">
                        <label for="employee_id" class="form-label">Employee ID</label>
                        <input type="text" class="form-control" id="employee_id" name="employee_id" placeholder="Enter Employee ID" required>
                    </div>
                    <div class="col-md-5">
                        <label for="employee_name" class="form-label">Employee Name</label>
                        <input type="text" class="form-control" id="employee_name" name="employee_name" placeholder="Enter Employee Name" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-success w-100">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Employees Table -->
    <div class="card">
        <div class="card-header">
            <h4>Current Employees</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Employee ID</th>
                            <th>Employee Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                                <td>
                                    <form method="post" class="update-form d-none" id="form_<?php echo $row['employee_id']; ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($row['employee_id']); ?>">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="new_employee_name" 
                                                   value="<?php echo htmlspecialchars($row['employee_name']); ?>">
                                            <button type="submit" class="btn btn-success btn-sm">Save</button>
                                            <button type="button" class="btn btn-secondary btn-sm cancel-edit">Cancel</button>
                                        </div>
                                    </form>
                                    <span class="employee-name"><?php echo htmlspecialchars($row['employee_name']); ?></span>
                                </td>
                                <td>
                                    <a href="index.php?search_employee=<?php echo urlencode($row['employee_id']); ?>" 
                                       class="btn btn-info btn-sm">View Records</a>
                                    <button class="btn btn-warning btn-sm edit-btn">Edit</button>
                                    <button type="button"
                                            class="btn btn-danger btn-sm unassign-btn"
                                            data-employee-id="<?php echo htmlspecialchars($row['employee_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-employee-name="<?php echo htmlspecialchars($row['employee_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        Unassign
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="app-modal" id="unassignModal" aria-hidden="true">
    <div class="app-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="unassignModalTitle">
        <div class="app-modal-header">
            <div>
                <span class="section-kicker">Employee Assignment</span>
                <h3 id="unassignModalTitle">Confirm Unassign</h3>
            </div>
            <button type="button" class="app-modal-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <p id="unassignModalText" class="mb-3"></p>
        <div class="app-modal-warning">
            The employee assignment will be removed. Attendance logs will remain in the system.
        </div>
        <form method="post" class="app-form">
            <input type="hidden" name="action" value="unassign">
            <input type="hidden" name="employee_id" id="unassignEmployeeId">
            <div class="app-modal-actions">
                <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Unassign</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addButton = document.getElementById('toggleAssignForm');
    const assignForm = document.getElementById('assignEmployeeForm');
    const closeButton = document.getElementById('closeAssignForm');
    
    if (addButton && assignForm) {
        addButton.addEventListener('click', function() {
            assignForm.classList.toggle('show');
        });

        closeButton.addEventListener('click', function() {
            assignForm.classList.remove('show');
        });
    }

    // Handle edit button clicks
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            row.querySelector('.employee-name').style.display = 'none';
            row.querySelector('.update-form').classList.remove('d-none');
        });
    });

    // Handle cancel button clicks
    document.querySelectorAll('.cancel-edit').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            row.querySelector('.employee-name').style.display = '';
            row.querySelector('.update-form').classList.add('d-none');
        });
    });

    if (document.querySelector('.app-flash-danger') && assignForm) {
        assignForm.classList.add('show');
    }

    const unassignModal = document.getElementById('unassignModal');
    const unassignEmployeeId = document.getElementById('unassignEmployeeId');
    const unassignModalText = document.getElementById('unassignModalText');

    function showUnassignModal(employeeId, employeeName) {
        unassignEmployeeId.value = employeeId;
        unassignModalText.textContent = 'Unassign ' + employeeName + ' (ID: ' + employeeId + ')?';
        unassignModal.classList.add('is-open');
        unassignModal.setAttribute('aria-hidden', 'false');
        window.lockPageScroll();
    }

    function hideUnassignModal() {
        unassignModal.classList.remove('is-open');
        unassignModal.setAttribute('aria-hidden', 'true');
        window.unlockPageScroll();
    }

    document.querySelectorAll('.unassign-btn').forEach(button => {
        button.addEventListener('click', function() {
            showUnassignModal(this.dataset.employeeId, this.dataset.employeeName);
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(button => {
        button.addEventListener('click', hideUnassignModal);
    });

    unassignModal.addEventListener('click', function(event) {
        if(event.target === unassignModal) {
            hideUnassignModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if(event.key === 'Escape' && unassignModal.classList.contains('is-open')) {
            hideUnassignModal();
        }
    });
});
</script>

<style>
.update-form.d-none {
    display: none !important;
}
.employee-name {
    display: inline-block;
    min-height: 24px;
}
.card-header h4 {
    margin-bottom: 0;
}
#assignEmployeeForm {
    transition: all 0.3s ease;
}
.collapse {
    display: none;
}
.collapse.show {
    display: block;
}
.app-card-close {
    cursor: pointer;
    border: 0;
    background: transparent;
    color: #ffffff;
    font-size: 1.4rem;
    line-height: 1;
}
</style>

<footer style="margin-top: auto; text-align: center; padding: 20px;">
    <?php include 'footer.php'; ?>
</footer>
