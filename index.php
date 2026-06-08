<?php
// index.php
include 'db.php';

function flashMessage($type, $title, $message) {
    $_SESSION['message'] = '<div class="app-flash app-flash-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '" role="status">'
        . '<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>'
        . '<span>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</span>'
        . '<button type="button" class="app-flash-close" aria-label="Dismiss">&times;</button>'
        . '</div>';
}

// Handle deletion based on selected option
if(isset($_POST['delete_records'])) {
    $delete_option = $_POST['delete_option'];

    if($delete_option === 'all') {
        // Delete all records from both biometrics_logs and employees tables
        $delete_logs = "TRUNCATE TABLE biometrics_logs";
        $delete_employees = "TRUNCATE TABLE employees";
        
        // Start transaction to ensure both operations complete
        $mysqli->begin_transaction();
        
        try {
            $mysqli->query($delete_logs);
            $mysqli->query($delete_employees);
            $mysqli->commit();
            flashMessage('success', 'Records deleted', 'All records and employee assignments have been deleted successfully.');
        } catch (Exception $e) {
            $mysqli->rollback();
            flashMessage('danger', 'Delete failed', 'Error deleting records.');
        }
    } elseif($delete_option === 'employee') {
        // Delete records by Employee ID
        $employee_id = $mysqli->real_escape_string($_POST['delete_employee_id']);
        if(empty($employee_id)) {
            flashMessage('warning', 'Missing employee ID', 'Please provide an Employee ID for deletion.');
        } else {
            $delete_query = "DELETE FROM biometrics_logs WHERE employee_id = '$employee_id'";
            if($mysqli->query($delete_query)) {
                flashMessage('success', 'Records deleted', "Records for Employee ID $employee_id have been deleted successfully.");
            } else {
                flashMessage('danger', 'Delete failed', "Error deleting records for Employee ID $employee_id.");
            }
        }
    } elseif($delete_option === 'month') {
        // Delete records by Month (input format: YYYY-MM)
        $month = $_POST['delete_month'];
        if(empty($month)) {
            flashMessage('warning', 'Missing month', 'Please provide a month for deletion.');
        } else {
            $start_date = $month . "-01";
            $end_date = date("Y-m-t", strtotime($start_date)); // gets last day of the month
            $delete_query = "DELETE FROM biometrics_logs WHERE log_date BETWEEN '$start_date' AND '$end_date'";
            if($mysqli->query($delete_query)) {
                flashMessage('success', 'Records deleted', "Records for $month have been deleted successfully.");
            } else {
                flashMessage('danger', 'Delete failed', "Error deleting records for $month.");
            }
        }
    } elseif($delete_option === 'range') {
        // Delete records by a range of dates
        $from_date = $_POST['delete_from_date'];
        $to_date = $_POST['delete_to_date'];
        if(empty($from_date) || empty($to_date)) {
            flashMessage('warning', 'Missing date range', 'Please provide both start and end dates for deletion.');
        } else {
            $delete_query = "DELETE FROM biometrics_logs WHERE log_date BETWEEN '$from_date' AND '$to_date'";
            if($mysqli->query($delete_query)) {
                flashMessage('success', 'Records deleted', "Records from $from_date to $to_date have been deleted successfully.");
            } else {
                flashMessage('danger', 'Delete failed', 'Error deleting records for the specified date range.');
            }
        }
    }
    
    header("Location: index.php");
    exit();
}

// Show the newest log dates first unless the user explicitly asks otherwise.
$order = (isset($_GET['order']) && $_GET['order'] === 'asc') ? 'ASC' : 'DESC';
$new_order = $order === 'ASC' ? 'desc' : 'asc';
$orderParam = strtolower($order);

// Get search parameters
$search_employee = isset($_GET['search_employee']) ? $_GET['search_employee'] : '';
$search_from_date = isset($_GET['search_from_date']) ? $_GET['search_from_date'] : '';
$search_to_date = isset($_GET['search_to_date']) ? $_GET['search_to_date'] : '';

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Build query with search conditions
$query = "SELECT b.*, e.employee_name FROM biometrics_logs b LEFT JOIN employees e ON b.employee_id = e.employee_id WHERE 1=1";
if (!empty($search_employee)) {
    $search_employee = $mysqli->real_escape_string($search_employee);
    $query .= " AND b.employee_id LIKE '%$search_employee%'";
}
if (!empty($search_from_date)) {
    $search_from_date = $mysqli->real_escape_string($search_from_date);
    $query .= " AND b.log_date >= '$search_from_date'";
}
if (!empty($search_to_date)) {
    $search_to_date = $mysqli->real_escape_string($search_to_date);
    $query .= " AND b.log_date <= '$search_to_date'";
}

// Get total records for pagination
$total_records_query = $query;
$total_records_result = $mysqli->query($total_records_query);
$total_records = $total_records_result->num_rows;
$total_pages = ceil($total_records / $records_per_page);

// Add sorting and pagination to main query
$query .= " ORDER BY b.log_date $order, b.employee_id ASC LIMIT $offset, $records_per_page";

// Fetch filtered records
$result = $mysqli->query($query);

include 'header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
* {
    font-family: 'Poppins', sans-serif;
}

.container {
    max-width: 100%;
    padding: 0 15px;
}

.table {
    width: 100%;
    margin: 0 auto;
    text-align: center;
}

.table th, .table td {
    text-align: center;
    vertical-align: middle;
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

@media (max-width: 1200px) {
    .container {
        max-width: 100%;
        padding: 0 10px;
    }
}
</style>

<?php
// Display message if set in session
if(isset($_SESSION['message'])) {
    echo $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php if (!empty($search_employee)): 
            // Fetch employee name
            $emp_query = "SELECT employee_name FROM employees WHERE employee_id = '" . $mysqli->real_escape_string($search_employee) . "'";
            $emp_result = $mysqli->query($emp_query);
            $emp_name = $emp_result->fetch_assoc();
            if ($emp_name): ?>
                <div class="text-muted text-center">
                    Viewing records for: <?php echo htmlspecialchars($emp_name['employee_name']); ?> 
                    (ID: <?php echo htmlspecialchars($search_employee); ?>)
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="mb-3 text-center">
    <a href="import.php" class="btn btn-info">Import Data</a>
    <a href="export.php" class="btn btn-success">Export to Excel</a>
    <?php if (!empty($search_employee)): ?>
        <a href="index.php" class="btn btn-secondary ms-2" style="margin-left: 10px;">Show All Records</a>
    <?php endif; ?>
</div>

<!-- Search Form -->
<form method="get" class="mb-4">
    <div class="row">
        <div class="col-md-2">
            <div class="form-group">
                <label for="search_employee">Employee ID:</label>
                <input type="text" class="form-control" id="search_employee" name="search_employee" 
                       value="<?php echo htmlspecialchars($search_employee); ?>">
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label for="search_from_date">From Date:</label>
                <input type="date" class="form-control" id="search_from_date" name="search_from_date"
                       value="<?php echo htmlspecialchars($search_from_date); ?>">
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label for="search_to_date">To Date:</label>
                <input type="date" class="form-control" id="search_to_date" name="search_to_date"
                       value="<?php echo htmlspecialchars($search_to_date); ?>">
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 5px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    Search
                </button>
                <a href="index.php" class="btn btn-secondary" style="background-color: #ffe066; color: #000;">Reset</a>
            </div>
        </div>
    </div>
    <input type="hidden" name="order" value="<?php echo htmlspecialchars($orderParam); ?>">
</form>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Employee ID</th>
                <th>Employee Name</th>
                <th>
                    <a href="?order=<?php echo $new_order; ?>&search_employee=<?php echo urlencode($search_employee); ?>&search_from_date=<?php echo urlencode($search_from_date); ?>&search_to_date=<?php echo urlencode($search_to_date); ?>&page=<?php echo $page; ?>">Log Date 
                        <?php echo $order === 'ASC' ? '▲' : '▼'; ?>
                    </a>
                </th>
                <th>Day</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Rendered Hours</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['employee_id']; ?></td>
                    <td><?php echo $row['employee_name'] ?? ''; ?></td>
                    <td><?php echo $row['log_date']; ?></td>
                    <td><?php echo date('l', strtotime($row['log_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($row['time_in'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($row['time_out'])); ?></td>
                    <td>
                        <?php 
                            $time_in = strtotime($row['time_in']);
                            $time_out = strtotime($row['time_out']);
                            
                            // Convert 18:00 (6 PM) to proper timestamp for calculation
                            if(date('H', $time_out) == '18') {
                                $time_out = strtotime('18:00:00', $time_out);
                            }
                            
                            // Subtract 1 hour for lunch break (12nn-1pm)
                            $total_minutes = round((($time_out - $time_in) / 60) - 60);
                            $hours = floor($total_minutes / 60);
                            $minutes = $total_minutes % 60;
                            echo $hours . ' hours ' . $minutes . ' minutes';
                        ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-end mb-3 py-4">
    <button type="button" class="btn btn-danger btn-action" id="openDeleteModal">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
        </svg>
        Delete Log Records
    </button>
</div>

<div class="app-modal" id="deleteModal" aria-hidden="true">
    <div class="app-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
        <div class="app-modal-header">
            <div>
                <span class="section-kicker">Delete Records</span>
                <h3 id="deleteModalTitle">Confirm Deletion</h3>
            </div>
            <button type="button" class="app-modal-close" data-modal-close aria-label="Close">&times;</button>
        </div>

        <form method="post" id="deleteRecordsForm" class="app-form">
            <div class="form-group mb-3">
                <label for="delete_option">Delete Option</label>
                <select name="delete_option" id="delete_option" class="form-control">
                    <option value="all">All Records</option>
                    <option value="employee">By Employee ID</option>
                    <option value="month">By Month</option>
                    <option value="range">By Range of Dates</option>
                </select>
            </div>
            <div id="employeeInput" class="filter-panel">
                <div class="form-group mb-3">
                    <label for="delete_employee_id">Employee ID</label>
                    <input type="text" name="delete_employee_id" id="delete_employee_id" class="form-control" placeholder="Employee ID">
                </div>
            </div>
            <div id="monthInput" class="filter-panel">
                <div class="form-group mb-3">
                    <label for="delete_month">Month</label>
                    <input type="month" name="delete_month" id="delete_month" class="form-control">
                </div>
            </div>
            <div id="rangeInput" class="filter-panel">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="delete_from_date">From Date</label>
                        <input type="date" name="delete_from_date" id="delete_from_date" class="form-control">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="delete_to_date">To Date</label>
                        <input type="date" name="delete_to_date" id="delete_to_date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="app-modal-warning">
                Deleted attendance records cannot be restored from this screen.
            </div>
            <div class="app-modal-actions">
                <button type="button" class="btn btn-light" data-modal-close>Cancel</button>
                <button type="submit" name="delete_records" class="btn btn-danger">Confirm Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    var deleteModal = document.getElementById('deleteModal');
    var openDeleteModal = document.getElementById('openDeleteModal');
    var deleteOption = document.getElementById('delete_option');

    function toggleDeleteInputs() {
        var panels = {
            employee: document.getElementById('employeeInput'),
            month: document.getElementById('monthInput'),
            range: document.getElementById('rangeInput')
        };
        Object.keys(panels).forEach(function(key) {
            panels[key].classList.remove('is-visible');
        });

        var option = deleteOption.value;
        if(panels[option]) {
            panels[option].classList.add('is-visible');
        }
    }

    function showDeleteModal() {
        deleteModal.classList.add('is-open');
        deleteModal.setAttribute('aria-hidden', 'false');
        window.lockPageScroll();
        toggleDeleteInputs();
    }

    function hideDeleteModal() {
        deleteModal.classList.remove('is-open');
        deleteModal.setAttribute('aria-hidden', 'true');
        window.unlockPageScroll();
    }

    openDeleteModal.addEventListener('click', showDeleteModal);
    deleteOption.addEventListener('change', toggleDeleteInputs);

    document.querySelectorAll('[data-modal-close]').forEach(function(button) {
        button.addEventListener('click', hideDeleteModal);
    });

    deleteModal.addEventListener('click', function(event) {
        if(event.target === deleteModal) {
            hideDeleteModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if(event.key === 'Escape' && deleteModal.classList.contains('is-open')) {
            hideDeleteModal();
        }
    });

    toggleDeleteInputs();
</script>

<!-- Pagination -->
<nav>
    <ul class="pagination justify-content-center">
        <?php if($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo ($page-1); ?>&order=<?php echo $orderParam; ?>&search_employee=<?php echo urlencode($search_employee); ?>&search_from_date=<?php echo urlencode($search_from_date); ?>&search_to_date=<?php echo urlencode($search_to_date); ?>">Previous</a>
            </li>
        <?php endif; ?>
        
        <?php 
        // Show first 10 pages or second 10 pages based on current page
        $start_page = $page <= 10 ? 1 : 11;
        $end_page = $page <= 10 ? min(10, $total_pages) : min(20, $total_pages);
        
        for($i = $start_page; $i <= $end_page; $i++): 
        ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&order=<?php echo $orderParam; ?>&search_employee=<?php echo urlencode($search_employee); ?>&search_from_date=<?php echo urlencode($search_from_date); ?>&search_to_date=<?php echo urlencode($search_to_date); ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        
        <?php if($page < $total_pages && $total_pages > 10): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo ($page <= 10 ? 11 : $page+1); ?>&order=<?php echo $orderParam; ?>&search_employee=<?php echo urlencode($search_employee); ?>&search_from_date=<?php echo urlencode($search_from_date); ?>&search_to_date=<?php echo urlencode($search_to_date); ?>">Next</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>

<footer style="margin-top: auto; text-align: center; padding: 20px;">
    <?php include 'footer.php'; ?>
</footer>
