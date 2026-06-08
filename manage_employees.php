<?php
include 'db.php';

function fetchEmployees($mysqli) {
    $result = $mysqli->query("SELECT * FROM employees ORDER BY employee_name");
    $rows = [];

    if($result) {
        while($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function renderEmployeesContent($rows) {
    ob_start();
    ?>
    <div class="section-heading">
        <div>
            <span class="section-kicker">Employee Directory</span>
            <h2>Manage Employees</h2>
        </div>
        <div>
            <button class="btn btn-primary btn-action" type="button" id="toggleAssignForm">Add New Employee</button>
            <a href="index.php" class="btn btn-secondary">Back to Logs</a>
        </div>
    </div>

    <div class="collapse mb-4" id="assignEmployeeForm">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Assign New Employee</h4>
                <button type="button" class="app-card-close" aria-label="Close" id="closeAssignForm">&times;</button>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3" id="assignEmployeeActionForm">
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
                        <?php if(empty($rows)): ?>
                            <tr>
                                <td colspan="3" class="text-muted">No employees assigned yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($rows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                                    <td>
                                        <form method="post" class="update-form d-none" id="form_<?php echo htmlspecialchars($row['employee_id']); ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($row['employee_id']); ?>">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="new_employee_name" value="<?php echo htmlspecialchars($row['employee_name']); ?>">
                                                <button type="submit" class="btn btn-success btn-sm">Save</button>
                                                <button type="button" class="btn btn-secondary btn-sm cancel-edit">Cancel</button>
                                            </div>
                                        </form>
                                        <span class="employee-name"><?php echo htmlspecialchars($row['employee_name']); ?></span>
                                    </td>
                                    <td>
                                        <a href="index.php?search_employee=<?php echo urlencode($row['employee_id']); ?>" class="btn btn-info btn-sm">View Records</a>
                                        <button class="btn btn-warning btn-sm edit-btn" type="button">Edit</button>
                                        <button
                                            type="button"
                                            class="btn btn-danger btn-sm unassign-btn"
                                            data-employee-id="<?php echo htmlspecialchars($row['employee_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-employee-name="<?php echo htmlspecialchars($row['employee_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            Unassign
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="app-modal" id="unassignModal" aria-hidden="true">
        <div class="app-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="unassignModalTitle">
            <div class="app-modal-header">
                <div>
                    <span class="section-kicker">Employee Assignment</span>
                    <h3 id="unassignModalTitle">Confirm Unassign</h3>
                </div>
                <button type="button" class="app-modal-close" data-employee-modal-close aria-label="Close">&times;</button>
            </div>
            <p id="unassignModalText" class="mb-3"></p>
            <div class="app-modal-warning">
                The employee assignment will be removed. Attendance logs will remain in the system.
            </div>
            <form method="post" class="app-form" id="unassignEmployeeForm">
                <input type="hidden" name="action" value="unassign">
                <input type="hidden" name="employee_id" id="unassignEmployeeId">
                <div class="app-modal-actions">
                    <button type="button" class="btn btn-light" data-employee-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Unassign</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function handleEmployeeAction($mysqli, $post) {
    $action = $post['action'] ?? '';

    if($action === 'assign') {
        $employeeId = $mysqli->real_escape_string(trim($post['employee_id'] ?? ''));
        $employeeName = $mysqli->real_escape_string(trim($post['employee_name'] ?? ''));

        if($employeeId === '' || $employeeName === '') {
            return ['type' => 'warning', 'title' => 'Missing employee details', 'message' => 'Please provide both employee ID and employee name.'];
        }

        $sql = "INSERT INTO employees (employee_id, employee_name) VALUES ('$employeeId', '$employeeName')";
        if($mysqli->query($sql)) {
            return ['type' => 'success', 'title' => 'Employee assigned', 'message' => 'Employee assigned successfully.'];
        }
        return ['type' => 'danger', 'title' => 'Assign failed', 'message' => 'Error assigning employee.'];
    }

    if($action === 'update') {
        $employeeId = $mysqli->real_escape_string(trim($post['employee_id'] ?? ''));
        $employeeName = $mysqli->real_escape_string(trim($post['new_employee_name'] ?? ''));

        if($employeeId === '' || $employeeName === '') {
            return ['type' => 'warning', 'title' => 'Missing employee details', 'message' => 'Please provide both employee ID and employee name.'];
        }

        $sql = "UPDATE employees SET employee_name = '$employeeName' WHERE employee_id = '$employeeId'";
        if($mysqli->query($sql)) {
            return ['type' => 'success', 'title' => 'Employee updated', 'message' => 'Employee updated successfully.'];
        }
        return ['type' => 'danger', 'title' => 'Update failed', 'message' => 'Error updating employee.'];
    }

    if($action === 'unassign') {
        $employeeId = $mysqli->real_escape_string(trim($post['employee_id'] ?? ''));

        if($employeeId === '') {
            return ['type' => 'warning', 'title' => 'Missing employee ID', 'message' => 'Please choose an employee to unassign.'];
        }

        $sql = "DELETE FROM employees WHERE employee_id = '$employeeId'";
        if($mysqli->query($sql)) {
            return ['type' => 'success', 'title' => 'Employee unassigned', 'message' => 'Employee unassigned successfully.'];
        }
        return ['type' => 'danger', 'title' => 'Unassign failed', 'message' => 'Error unassigning employee.'];
    }

    return ['type' => 'warning', 'title' => 'Missing action', 'message' => 'Please choose an employee action.'];
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $flash = handleEmployeeAction($mysqli, $_POST);

    if(app_is_ajax_request()) {
        app_json_response([
            'ok' => $flash['type'] === 'success',
            'flash_html' => app_flash_html($flash['type'], $flash['title'], $flash['message']),
            'content_html' => renderEmployeesContent(fetchEmployees($mysqli))
        ], $flash['type'] === 'success' ? 200 : 422);
    }

    app_set_flash($flash['type'], $flash['title'], $flash['message']);
    header('Location: manage_employees.php');
    exit();
}

include 'header.php';
?>

<div id="employeesContent"><?php echo renderEmployeesContent(fetchEmployees($mysqli)); ?></div>

<script>
$(function () {
    var $employeesContent = $('#employeesContent');

    function renderEmployeesContentHtml(html) {
        $employeesContent.html(html);
    }

    function showUnassignModal(employeeId, employeeName) {
        $('#unassignEmployeeId').val(employeeId);
        $('#unassignModalText').text('Unassign ' + employeeName + ' (ID: ' + employeeId + ')?');
        $('#unassignModal').addClass('is-open').attr('aria-hidden', 'false');
        window.lockPageScroll();
    }

    function hideUnassignModal() {
        $('#unassignModal').removeClass('is-open').attr('aria-hidden', 'true');
        window.unlockPageScroll();
    }

    $employeesContent.on('click', '#toggleAssignForm', function () {
        $('#assignEmployeeForm').toggleClass('show');
    });

    $employeesContent.on('click', '#closeAssignForm', function () {
        $('#assignEmployeeForm').removeClass('show');
    });

    $employeesContent.on('click', '.edit-btn', function () {
        var $row = $(this).closest('tr');
        $row.find('.employee-name').hide();
        $row.find('.update-form').removeClass('d-none');
    });

    $employeesContent.on('click', '.cancel-edit', function () {
        var $row = $(this).closest('tr');
        $row.find('.employee-name').show();
        $row.find('.update-form').addClass('d-none');
    });

    $employeesContent.on('click', '.unassign-btn', function () {
        showUnassignModal($(this).data('employeeId'), $(this).data('employeeName'));
    });

    $employeesContent.on('click', '[data-employee-modal-close]', function () {
        hideUnassignModal();
    });

    $employeesContent.on('click', '#unassignModal', function (event) {
        if (event.target === this) {
            hideUnassignModal();
        }
    });

    $employeesContent.on('submit', '#assignEmployeeActionForm, .update-form, #unassignEmployeeForm', function (event) {
        event.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]').last();
        var originalText = $button.text();

        $button.prop('disabled', true);

        $.ajax({
            url: 'manage_employees.php',
            method: 'POST',
            data: $form.serialize(),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).done(function (response) {
            window.setAppFlash(response.flash_html || '');
            renderEmployeesContentHtml(response.content_html || '');
            if ($('#unassignModal').length) {
                hideUnassignModal();
            }
        }).fail(function (xhr) {
            if (xhr.responseJSON && xhr.responseJSON.flash_html) {
                window.setAppFlash(xhr.responseJSON.flash_html);
            } else {
                window.handleAjaxFailure('Unable to update employee records.');
            }
        }).always(function () {
            $button.prop('disabled', false).text(originalText);
        });
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && $('#unassignModal').hasClass('is-open')) {
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
