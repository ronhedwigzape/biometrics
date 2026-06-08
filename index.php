<?php
include 'db.php';

function calculateRenderedHoursText($timeInValue, $timeOutValue) {
    $timeIn = strtotime($timeInValue);
    $timeOut = strtotime($timeOutValue);

    if($timeIn === false || $timeOut === false) {
        return '0 hours 0 minutes';
    }

    if(date('H', $timeOut) == '18') {
        $timeOut = strtotime('18:00:00', $timeOut);
    }

    $totalMinutes = round((($timeOut - $timeIn) / 60) - 60);
    if($totalMinutes < 0) {
        $totalMinutes = 0;
    }

    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    return $hours . ' hours ' . $minutes . ' minutes';
}

function getIndexState($mysqli, $source) {
    $order = (isset($source['order']) && $source['order'] === 'asc') ? 'ASC' : 'DESC';
    $newOrder = $order === 'ASC' ? 'desc' : 'asc';
    $orderParam = strtolower($order);

    $searchEmployee = isset($source['search_employee']) ? trim($source['search_employee']) : '';
    $searchFromDate = isset($source['search_from_date']) ? trim($source['search_from_date']) : '';
    $searchToDate = isset($source['search_to_date']) ? trim($source['search_to_date']) : '';

    $recordsPerPage = 10;
    $requestedPage = isset($source['page']) ? (int) $source['page'] : 1;
    if($requestedPage < 1) {
        $requestedPage = 1;
    }

    $whereParts = ['1=1'];
    if($searchEmployee !== '') {
        $escapedEmployee = $mysqli->real_escape_string($searchEmployee);
        $whereParts[] = "b.employee_id LIKE '%$escapedEmployee%'";
    }
    if($searchFromDate !== '') {
        $escapedFromDate = $mysqli->real_escape_string($searchFromDate);
        $whereParts[] = "b.log_date >= '$escapedFromDate'";
    }
    if($searchToDate !== '') {
        $escapedToDate = $mysqli->real_escape_string($searchToDate);
        $whereParts[] = "b.log_date <= '$escapedToDate'";
    }

    $baseQuery = "FROM biometrics_logs b LEFT JOIN employees e ON b.employee_id = e.employee_id WHERE " . implode(' AND ', $whereParts);
    $countQuery = "SELECT COUNT(*) AS total " . $baseQuery;
    $countResult = $mysqli->query($countQuery);
    $totalRecords = $countResult ? (int) $countResult->fetch_assoc()['total'] : 0;
    $totalPages = max(1, (int) ceil($totalRecords / $recordsPerPage));
    $page = min($requestedPage, $totalPages);
    $offset = ($page - 1) * $recordsPerPage;

    $query = "SELECT b.*, e.employee_name " . $baseQuery . " ORDER BY b.log_date $order, b.employee_id ASC LIMIT $offset, $recordsPerPage";
    $result = $mysqli->query($query);

    $rows = [];
    if($result) {
        while($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $employeeDisplayName = '';
    if($searchEmployee !== '') {
        $escapedEmployee = $mysqli->real_escape_string($searchEmployee);
        $employeeResult = $mysqli->query("SELECT employee_name FROM employees WHERE employee_id = '$escapedEmployee'");
        if($employeeResult && ($employeeRow = $employeeResult->fetch_assoc())) {
            $employeeDisplayName = $employeeRow['employee_name'];
        }
    }

    return [
        'order' => $order,
        'order_param' => $orderParam,
        'new_order' => $newOrder,
        'search_employee' => $searchEmployee,
        'search_from_date' => $searchFromDate,
        'search_to_date' => $searchToDate,
        'records_per_page' => $recordsPerPage,
        'page' => $page,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'rows' => $rows,
        'employee_display_name' => $employeeDisplayName,
    ];
}

function renderIndexPagination($state) {
    if($state['total_pages'] <= 1) {
        return '';
    }

    ob_start();
    ?>
    <nav>
        <ul class="pagination justify-content-center">
            <?php if($state['page'] > 1): ?>
                <li class="page-item">
                    <a class="page-link js-index-link" href="?page=<?php echo ($state['page'] - 1); ?>&order=<?php echo $state['order_param']; ?>&search_employee=<?php echo urlencode($state['search_employee']); ?>&search_from_date=<?php echo urlencode($state['search_from_date']); ?>&search_to_date=<?php echo urlencode($state['search_to_date']); ?>">Previous</a>
                </li>
            <?php endif; ?>

            <?php
            $startPage = $state['page'] <= 10 ? 1 : 11;
            $endPage = $state['page'] <= 10 ? min(10, $state['total_pages']) : min(20, $state['total_pages']);
            for($i = $startPage; $i <= $endPage; $i++):
            ?>
                <li class="page-item <?php echo $i == $state['page'] ? 'active' : ''; ?>">
                    <a class="page-link js-index-link" href="?page=<?php echo $i; ?>&order=<?php echo $state['order_param']; ?>&search_employee=<?php echo urlencode($state['search_employee']); ?>&search_from_date=<?php echo urlencode($state['search_from_date']); ?>&search_to_date=<?php echo urlencode($state['search_to_date']); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <?php if($state['page'] < $state['total_pages'] && $state['total_pages'] > 10): ?>
                <li class="page-item">
                    <a class="page-link js-index-link" href="?page=<?php echo ($state['page'] <= 10 ? 11 : $state['page'] + 1); ?>&order=<?php echo $state['order_param']; ?>&search_employee=<?php echo urlencode($state['search_employee']); ?>&search_from_date=<?php echo urlencode($state['search_from_date']); ?>&search_to_date=<?php echo urlencode($state['search_to_date']); ?>">Next</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php
    return ob_get_clean();
}

function renderIndexContent($state) {
    ob_start();
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <?php if ($state['search_employee'] !== '' && $state['employee_display_name'] !== ''): ?>
                <div class="text-muted text-center">
                    Viewing records for: <?php echo htmlspecialchars($state['employee_display_name']); ?>
                    (ID: <?php echo htmlspecialchars($state['search_employee']); ?>)
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-3 text-center">
        <a href="import.php" class="btn btn-info">Import Data</a>
        <a href="export.php" class="btn btn-success">Export to Excel</a>
        <?php if ($state['search_employee'] !== '' || $state['search_from_date'] !== '' || $state['search_to_date'] !== ''): ?>
            <a href="index.php" class="btn btn-secondary ms-2 js-index-link" style="margin-left: 10px;">Show All Records</a>
        <?php endif; ?>
    </div>

    <form method="get" class="mb-4" id="searchForm">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="search_employee">Employee ID:</label>
                    <input type="text" class="form-control" id="search_employee" name="search_employee" value="<?php echo htmlspecialchars($state['search_employee']); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="search_from_date">From Date:</label>
                    <input type="date" class="form-control" id="search_from_date" name="search_from_date" value="<?php echo htmlspecialchars($state['search_from_date']); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="search_to_date">To Date:</label>
                    <input type="date" class="form-control" id="search_to_date" name="search_to_date" value="<?php echo htmlspecialchars($state['search_to_date']); ?>">
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
                    <a href="index.php" class="btn btn-secondary js-index-link" style="background-color: #ffe066; color: #000;">Reset</a>
                </div>
            </div>
        </div>
        <input type="hidden" name="order" value="<?php echo htmlspecialchars($state['order_param']); ?>">
    </form>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Employee Name</th>
                    <th>
                        <a class="js-index-link" href="?order=<?php echo $state['new_order']; ?>&search_employee=<?php echo urlencode($state['search_employee']); ?>&search_from_date=<?php echo urlencode($state['search_from_date']); ?>&search_to_date=<?php echo urlencode($state['search_to_date']); ?>&page=<?php echo $state['page']; ?>">
                            Log Date <?php echo $state['order'] === 'ASC' ? '&uarr;' : '&darr;'; ?>
                        </a>
                    </th>
                    <th>Day</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Rendered Hours</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($state['rows'])): ?>
                    <tr>
                        <td colspan="7" class="text-muted">No attendance records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($state['rows'] as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['employee_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['log_date']); ?></td>
                            <td><?php echo htmlspecialchars(date('l', strtotime($row['log_date']))); ?></td>
                            <td><?php echo htmlspecialchars(date('h:i A', strtotime($row['time_in']))); ?></td>
                            <td><?php echo htmlspecialchars(date('h:i A', strtotime($row['time_out']))); ?></td>
                            <td><?php echo htmlspecialchars(calculateRenderedHoursText($row['time_in'], $row['time_out'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
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
                <button type="button" class="app-modal-close" data-index-modal-close aria-label="Close">&times;</button>
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
                    <button type="button" class="btn btn-light" data-index-modal-close>Cancel</button>
                    <button type="submit" name="delete_records" class="btn btn-danger">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>

    <?php echo renderIndexPagination($state); ?>
    <?php
    return ob_get_clean();
}

function deleteRecords($mysqli, $post) {
    $deleteOption = $post['delete_option'] ?? '';

    if($deleteOption === 'all') {
        $deleteLogs = "TRUNCATE TABLE biometrics_logs";
        $deleteEmployees = "TRUNCATE TABLE employees";
        $mysqli->begin_transaction();

        try {
            $mysqli->query($deleteLogs);
            $mysqli->query($deleteEmployees);
            $mysqli->commit();
            return ['type' => 'success', 'title' => 'Records deleted', 'message' => 'All records and employee assignments have been deleted successfully.'];
        } catch (Exception $e) {
            $mysqli->rollback();
            return ['type' => 'danger', 'title' => 'Delete failed', 'message' => 'Error deleting records.'];
        }
    }

    if($deleteOption === 'employee') {
        $employeeId = $mysqli->real_escape_string(trim($post['delete_employee_id'] ?? ''));
        if($employeeId === '') {
            return ['type' => 'warning', 'title' => 'Missing employee ID', 'message' => 'Please provide an Employee ID for deletion.'];
        }

        $deleteQuery = "DELETE FROM biometrics_logs WHERE employee_id = '$employeeId'";
        if($mysqli->query($deleteQuery)) {
            return ['type' => 'success', 'title' => 'Records deleted', 'message' => "Records for Employee ID $employeeId have been deleted successfully."];
        }
        return ['type' => 'danger', 'title' => 'Delete failed', 'message' => "Error deleting records for Employee ID $employeeId."];
    }

    if($deleteOption === 'month') {
        $month = trim($post['delete_month'] ?? '');
        if($month === '') {
            return ['type' => 'warning', 'title' => 'Missing month', 'message' => 'Please provide a month for deletion.'];
        }

        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $deleteQuery = "DELETE FROM biometrics_logs WHERE log_date BETWEEN '$startDate' AND '$endDate'";
        if($mysqli->query($deleteQuery)) {
            return ['type' => 'success', 'title' => 'Records deleted', 'message' => "Records for $month have been deleted successfully."];
        }
        return ['type' => 'danger', 'title' => 'Delete failed', 'message' => "Error deleting records for $month."];
    }

    if($deleteOption === 'range') {
        $fromDate = trim($post['delete_from_date'] ?? '');
        $toDate = trim($post['delete_to_date'] ?? '');
        if($fromDate === '' || $toDate === '') {
            return ['type' => 'warning', 'title' => 'Missing date range', 'message' => 'Please provide both start and end dates for deletion.'];
        }

        $deleteQuery = "DELETE FROM biometrics_logs WHERE log_date BETWEEN '$fromDate' AND '$toDate'";
        if($mysqli->query($deleteQuery)) {
            return ['type' => 'success', 'title' => 'Records deleted', 'message' => "Records from $fromDate to $toDate have been deleted successfully."];
        }
        return ['type' => 'danger', 'title' => 'Delete failed', 'message' => 'Error deleting records for the specified date range.'];
    }

    return ['type' => 'warning', 'title' => 'Missing option', 'message' => 'Please choose a delete option.'];
}

if(isset($_POST['delete_records'])) {
    $flash = deleteRecords($mysqli, $_POST);

    if(app_is_ajax_request()) {
        app_json_response([
            'ok' => $flash['type'] === 'success',
            'flash_html' => app_flash_html($flash['type'], $flash['title'], $flash['message'])
        ], $flash['type'] === 'success' ? 200 : 422);
    }

    app_set_flash($flash['type'], $flash['title'], $flash['message']);
    header('Location: index.php');
    exit();
}

if(app_is_ajax_request() && ($_GET['ajax'] ?? '') === 'content') {
    $state = getIndexState($mysqli, $_GET);
    app_json_response([
        'ok' => true,
        'content_html' => renderIndexContent($state),
        'state' => [
            'page' => $state['page'],
            'order' => $state['order_param'],
            'search_employee' => $state['search_employee'],
            'search_from_date' => $state['search_from_date'],
            'search_to_date' => $state['search_to_date'],
        ]
    ]);
}

$state = getIndexState($mysqli, $_GET);

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

<div id="logsContent"><?php echo renderIndexContent($state); ?></div>

<script>
$(function () {
    var $logsContent = $('#logsContent');
    var currentIndexState = {
        page: '<?php echo $state['page']; ?>',
        order: '<?php echo $state['order_param']; ?>',
        search_employee: '<?php echo htmlspecialchars($state['search_employee'], ENT_QUOTES, 'UTF-8'); ?>',
        search_from_date: '<?php echo htmlspecialchars($state['search_from_date'], ENT_QUOTES, 'UTF-8'); ?>',
        search_to_date: '<?php echo htmlspecialchars($state['search_to_date'], ENT_QUOTES, 'UTF-8'); ?>'
    };

    function normalizeIndexState(state) {
        return {
            page: state.page || 1,
            order: state.order || 'desc',
            search_employee: state.search_employee || '',
            search_from_date: state.search_from_date || '',
            search_to_date: state.search_to_date || ''
        };
    }

    function readIndexStateFromQuery(url) {
        var fullUrl = new URL(url, window.location.origin + window.location.pathname);
        return normalizeIndexState({
            page: fullUrl.searchParams.get('page') || 1,
            order: fullUrl.searchParams.get('order') || 'desc',
            search_employee: fullUrl.searchParams.get('search_employee') || '',
            search_from_date: fullUrl.searchParams.get('search_from_date') || '',
            search_to_date: fullUrl.searchParams.get('search_to_date') || ''
        });
    }

    function toggleDeleteInputs() {
        var option = $('#delete_option').val();
        $('#employeeInput, #monthInput, #rangeInput').removeClass('is-visible');
        if (option === 'employee') {
            $('#employeeInput').addClass('is-visible');
        } else if (option === 'month') {
            $('#monthInput').addClass('is-visible');
        } else if (option === 'range') {
            $('#rangeInput').addClass('is-visible');
        }
    }

    function showDeleteModal() {
        $('#deleteModal').addClass('is-open').attr('aria-hidden', 'false');
        window.lockPageScroll();
        toggleDeleteInputs();
    }

    function hideDeleteModal() {
        $('#deleteModal').removeClass('is-open').attr('aria-hidden', 'true');
        window.unlockPageScroll();
    }

    function loadIndexContent(state) {
        currentIndexState = normalizeIndexState(state);

        $.ajax({
            url: 'index.php',
            method: 'GET',
            data: $.extend({ ajax: 'content' }, currentIndexState),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).done(function (response) {
            $logsContent.html(response.content_html);
            currentIndexState = normalizeIndexState(response.state || currentIndexState);

            var query = $.param(currentIndexState);
            window.history.replaceState({}, '', 'index.php?' + query);
            toggleDeleteInputs();
        }).fail(function () {
            window.handleAjaxFailure('Unable to load attendance records.');
        });
    }

    $logsContent.on('submit', '#searchForm', function (event) {
        event.preventDefault();
        loadIndexContent({
            page: 1,
            order: $(this).find('input[name="order"]').val() || 'desc',
            search_employee: $(this).find('input[name="search_employee"]').val(),
            search_from_date: $(this).find('input[name="search_from_date"]').val(),
            search_to_date: $(this).find('input[name="search_to_date"]').val()
        });
    });

    $logsContent.on('click', '.js-index-link', function (event) {
        var href = $(this).attr('href');
        if (!href || href.indexOf('index.php') !== 0 && href.indexOf('?') !== 0) {
            return;
        }
        event.preventDefault();
        loadIndexContent(readIndexStateFromQuery(href));
    });

    $logsContent.on('click', '#openDeleteModal', function () {
        showDeleteModal();
    });

    $logsContent.on('click', '[data-index-modal-close]', function () {
        hideDeleteModal();
    });

    $logsContent.on('change', '#delete_option', function () {
        toggleDeleteInputs();
    });

    $logsContent.on('click', '#deleteModal', function (event) {
        if (event.target === this) {
            hideDeleteModal();
        }
    });

    $logsContent.on('submit', '#deleteRecordsForm', function (event) {
        event.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        $button.prop('disabled', true).text('Deleting...');

        $.ajax({
            url: 'index.php',
            method: 'POST',
            data: $form.serialize() + '&delete_records=1',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).done(function (response) {
            window.setAppFlash(response.flash_html || '');
            hideDeleteModal();
            loadIndexContent(currentIndexState);
        }).fail(function (xhr) {
            if (xhr.responseJSON && xhr.responseJSON.flash_html) {
                window.setAppFlash(xhr.responseJSON.flash_html);
            } else {
                window.handleAjaxFailure('Unable to delete attendance records.');
            }
        }).always(function () {
            $button.prop('disabled', false).text('Confirm Delete');
        });
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && $('#deleteModal').hasClass('is-open')) {
            hideDeleteModal();
        }
    });

    toggleDeleteInputs();
});
</script>

<footer style="margin-top: auto; text-align: center; padding: 20px;">
    <?php include 'footer.php'; ?>
</footer>
