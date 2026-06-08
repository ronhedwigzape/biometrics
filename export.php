<?php
// export.php
include 'db.php';

function flashMessage($type, $title, $message) {
    $_SESSION['message'] = '<div class="app-flash app-flash-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '" role="status">'
        . '<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>'
        . '<span>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</span>'
        . '<button type="button" class="app-flash-close" aria-label="Dismiss">&times;</button>'
        . '</div>';
}

function xmlEscape($value) {
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function columnName($index) {
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function textLength($value) {
    $value = (string) $value;
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function renderedHours($timeIn, $timeOut) {
    $time_in = strtotime($timeIn);
    $time_out = strtotime($timeOut);

    if($time_in === false || $time_out === false) {
        return '00:00';
    }

    $total_minutes = round((($time_out - $time_in) / 60) - 60);
    if($total_minutes < 0) {
        $total_minutes = 0;
    }

    $hours = floor($total_minutes / 60);
    $minutes = $total_minutes % 60;
    return sprintf('%02d:%02d', $hours, $minutes);
}

function buildWorksheetXml($rows, $columnWidths) {
    $cols = '<cols>';
    foreach($columnWidths as $index => $width) {
        $column = $index + 1;
        $cols .= '<col min="' . $column . '" max="' . $column . '" width="' . $width . '" customWidth="1"/>';
    }
    $cols .= '</cols>';

    $sheetData = '<sheetData>';
    foreach($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 1;
        $sheetData .= '<row r="' . $excelRow . '">';
        foreach($row as $columnIndex => $value) {
            $cell = columnName($columnIndex + 1) . $excelRow;
            $style = $rowIndex === 0 ? ' s="1"' : '';
            $sheetData .= '<c r="' . $cell . '" t="inlineStr"' . $style . '><is><t>' . xmlEscape($value) . '</t></is></c>';
        }
        $sheetData .= '</row>';
    }
    $sheetData .= '</sheetData>';

    $lastColumn = columnName(count($rows[0]));
    $lastRow = count($rows);

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . $cols
        . $sheetData
        . '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>'
        . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
        . '</worksheet>';
}

function streamXlsx($filename, $rows) {
    if(!class_exists('ZipArchive')) {
        flashMessage('danger', 'Export failed', 'The PHP ZipArchive extension is required to create XLSX files.');
        header('Location: export.php');
        exit();
    }

    $columnWidths = [];
    foreach($rows as $row) {
        foreach($row as $index => $value) {
            $length = textLength($value);
            $columnWidths[$index] = max($columnWidths[$index] ?? 0, $length);
        }
    }

    foreach($columnWidths as $index => $length) {
        $columnWidths[$index] = min(100, max(12, $length + 3));
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
        flashMessage('danger', 'Export failed', 'Unable to create the temporary XLSX file.');
        header('Location: export.php');
        exit();
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>');
    $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
        . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>GVS Biometrics</Application></Properties>');
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
        . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Attendance Report</dc:title><dc:creator>GVS Biometrics</dc:creator>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
        . '</cp:coreProperties>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Attendance" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1864AB"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9E2EC"/></left><right style="thin"><color rgb="FFD9E2EC"/></right><top style="thin"><color rgb="FFD9E2EC"/></top><bottom style="thin"><color rgb="FFD9E2EC"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>');
    $zip->addFromString('xl/worksheets/sheet1.xml', buildWorksheetXml($rows, $columnWidths));
    $zip->close();

    if(ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: max-age=0');
    readfile($tmpFile);
    unlink($tmpFile);
    exit();
}

function buildExportQuery($mysqli, &$filename) {
    $filterType = $_POST['filter'] ?? '';
    $timestamp = date('Ymd_His');

    if($filterType === 'all') {
        $filename = 'Complete_Attendance_Report_' . $timestamp;
        return "SELECT b.*, e.employee_name
                FROM biometrics_logs b
                LEFT JOIN employees e ON b.employee_id = e.employee_id
                ORDER BY b.employee_id, b.log_date";
    }

    if($filterType === 'employee') {
        if(empty($_POST['employee_id'])) {
            flashMessage('danger', 'Missing employee ID', 'Please enter an Employee ID before exporting.');
            header('Location: export.php');
            exit();
        }
        $employee_id = $mysqli->real_escape_string(trim($_POST['employee_id']));
        $filename = 'Attendance_Report_EmpID' . preg_replace('/[^A-Za-z0-9_-]/', '', $employee_id) . '_' . $timestamp;
        return "SELECT b.*, e.employee_name
                FROM biometrics_logs b
                LEFT JOIN employees e ON b.employee_id = e.employee_id
                WHERE b.employee_id = '$employee_id'
                ORDER BY b.log_date";
    }

    if($filterType === 'monthly') {
        if(empty($_POST['month'])) {
            flashMessage('danger', 'Missing month', 'Please select a month before exporting.');
            header('Location: export.php');
            exit();
        }
        $monthValue = $mysqli->real_escape_string($_POST['month']);
        $yearMonth = explode('-', $monthValue);
        $year = $yearMonth[0] ?? '';
        $month = $yearMonth[1] ?? '';
        $filename = 'Monthly_Attendance_Report_' . date('Y_F', strtotime($year . '-' . $month . '-01')) . '_' . $timestamp;
        return "SELECT b.*, e.employee_name
                FROM biometrics_logs b
                LEFT JOIN employees e ON b.employee_id = e.employee_id
                WHERE YEAR(b.log_date) = '$year' AND MONTH(b.log_date) = '$month'
                ORDER BY b.employee_id, b.log_date";
    }

    if($filterType === 'range') {
        if(empty($_POST['from_date']) || empty($_POST['to_date'])) {
            flashMessage('danger', 'Missing date range', 'Please select both From and To dates before exporting.');
            header('Location: export.php');
            exit();
        }
        $from = $mysqli->real_escape_string($_POST['from_date']);
        $to = $mysqli->real_escape_string($_POST['to_date']);
        $filename = 'Attendance_Report_' . date('Ymd', strtotime($from)) . '_to_' . date('Ymd', strtotime($to)) . '_' . $timestamp;
        return "SELECT b.*, e.employee_name
                FROM biometrics_logs b
                LEFT JOIN employees e ON b.employee_id = e.employee_id
                WHERE b.log_date BETWEEN '$from' AND '$to'
                ORDER BY b.employee_id, b.log_date";
    }

    flashMessage('danger', 'Missing export filter', 'Please select an export filter.');
    header('Location: export.php');
    exit();
}

if(isset($_POST['export'])) {
    $filename = 'Attendance_Report_' . date('Ymd_His');
    $query = buildExportQuery($mysqli, $filename);
    $result = $mysqli->query($query);

    if(!$result || $result->num_rows === 0) {
        flashMessage('warning', 'No records found', 'No attendance records matched the selected export filter.');
        header('Location: export.php');
        exit();
    }

    $rows = [
        ['Employee ID', 'Employee Name', 'Date', 'Day', 'Time In', 'Time Out', 'Rendered Hours']
    ];

    while($row = $result->fetch_assoc()) {
        $timeIn = strtotime($row['time_in']);
        $timeOut = strtotime($row['time_out']);
        $employeeName = !empty($row['employee_name']) ? ucwords(strtolower($row['employee_name'])) : 'N/A';

        $rows[] = [
            str_pad((string) $row['employee_id'], 4, '0', STR_PAD_LEFT),
            $employeeName,
            date('Y-m-d', strtotime($row['log_date'])),
            date('l', strtotime($row['log_date'])),
            $timeIn !== false ? date('h:i A', $timeIn) : '',
            $timeOut !== false ? date('h:i A', $timeOut) : '',
            renderedHours($row['time_in'], $row['time_out']),
        ];
    }

    streamXlsx($filename, $rows);
}

include 'header.php';
?>

<?php
if(isset($_SESSION['message'])) {
    echo $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<section class="app-panel export-panel">
    <div class="section-heading">
        <div>
            <span class="section-kicker">Excel Export</span>
            <h2>Export Biometrics Data</h2>
        </div>
    </div>

    <form method="post" action="export.php" class="app-form">
        <div class="form-group">
            <label for="filter">Export Filter</label>
            <select name="filter" id="filter" class="form-control" required>
                <option value="">Select filter</option>
                <option value="all">All Records</option>
                <option value="employee">By Employee ID</option>
                <option value="monthly">By Month</option>
                <option value="range">By Date Range</option>
            </select>
        </div>

        <div id="employeeFilter" class="filter-panel">
            <div class="form-group">
                <label for="employee_id">Employee ID</label>
                <input type="text" name="employee_id" id="employee_id" class="form-control">
            </div>
        </div>

        <div id="monthFilter" class="filter-panel">
            <div class="form-group">
                <label for="month">Month</label>
                <input type="month" name="month" id="month" class="form-control">
            </div>
        </div>

        <div id="dateRange" class="filter-panel">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="from_date">From Date</label>
                    <input type="date" name="from_date" id="from_date" class="form-control">
                </div>
                <div class="form-group col-md-6">
                    <label for="to_date">To Date</label>
                    <input type="date" name="to_date" id="to_date" class="form-control">
                </div>
            </div>
        </div>

        <button type="submit" name="export" class="btn btn-primary btn-action">Export XLSX</button>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var filter = document.getElementById('filter');
    var panels = {
        employee: document.getElementById('employeeFilter'),
        monthly: document.getElementById('monthFilter'),
        range: document.getElementById('dateRange')
    };

    function toggleFilters() {
        Object.keys(panels).forEach(function (key) {
            panels[key].classList.toggle('is-visible', filter.value === key);
        });
    }

    filter.addEventListener('change', toggleFilters);
    toggleFilters();
});
</script>

<footer style="margin-top: auto; text-align: center; padding: 20px;">
    <?php include 'footer.php'; ?>
</footer>
