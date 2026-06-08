<?php
// import.php
include 'db.php';

function flashMessage($type, $title, $message) {
    $_SESSION['message'] = '<div class="app-flash app-flash-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '" role="status">'
        . '<strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>'
        . '<span>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</span>'
        . '<button type="button" class="app-flash-close" aria-label="Dismiss">&times;</button>'
        . '</div>';
}

function parseBiometricTimestamp($rawDateTime) {
    $rawDateTime = trim($rawDateTime);

    if (preg_match('/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2}) (?<hour>\d{2}):(?<minute>\d{2}):(?<second>\d{2})$/', $rawDateTime, $matches)) {
        if (!checkdate((int) $matches['month'], (int) $matches['day'], (int) $matches['year'])) {
            return null;
        }

        return [
            'date' => $matches['year'] . '-' . $matches['month'] . '-' . $matches['day'],
            'time' => $matches['hour'] . ':' . $matches['minute'] . ':' . $matches['second'],
        ];
    }

    return null;
}

if(isset($_POST['submit'])){
    $totalInserted = 0;
    $totalSkipped = 0;
    $fileCount = 0;
    $errorFiles = [];
    $importedMinDate = null;
    $importedMaxDate = null;
    $parsedMinDate = null;
    $parsedMaxDate = null;

    foreach($_FILES['datafile']['tmp_name'] as $fileIndex => $tmp_name) {
        if($_FILES['datafile']['error'][$fileIndex] == 0) {
            $fileCount++;
            $file = $tmp_name;
            $handle = fopen($file, 'r');
            $logs = [];
            $hasData = false;
            $invalidLineCount = 0;

            if($handle){
                while(($line = fgets($handle)) !== false){
                    $data = explode("\t", trim($line));
                    
                    if(count($data) >= 2){
                        $employee_id = trim($data[0]);
                        $datetime = trim($data[1]);
                        $parsedDateTime = parseBiometricTimestamp($datetime);

                        if($parsedDateTime === null){
                            $invalidLineCount++;
                            continue;
                        }

                        $hasData = true;
                        $date = $parsedDateTime['date'];
                        $time = $parsedDateTime['time'];
                        if($parsedMinDate === null || $date < $parsedMinDate) {
                            $parsedMinDate = $date;
                        }
                        if($parsedMaxDate === null || $date > $parsedMaxDate) {
                            $parsedMaxDate = $date;
                        }
                        
                        if(!isset($logs[$employee_id])){
                            $logs[$employee_id] = [];
                        }
                        if(!isset($logs[$employee_id][$date])){
                            $logs[$employee_id][$date] = ['in' => null, 'out' => null];
                        }
                        
                        // Assign the earliest time as "Time In"
                        if($logs[$employee_id][$date]['in'] === null || $time < $logs[$employee_id][$date]['in']){
                            $logs[$employee_id][$date]['in'] = $time;
                        }
                        
                        // Assign the latest time as "Time Out"
                        if($logs[$employee_id][$date]['out'] === null || $time > $logs[$employee_id][$date]['out']){
                            $logs[$employee_id][$date]['out'] = $time;
                        }
                    }
                }
                fclose($handle);

                if(!$hasData) {
                    $message = "No valid data found";
                    if($invalidLineCount > 0) {
                        $message .= "; skipped $invalidLineCount line(s) with invalid date/time";
                    }

                    $errorFiles[] = $_FILES['datafile']['name'][$fileIndex] . " ($message)";
                    continue;
                }

                if($invalidLineCount > 0) {
                    $errorFiles[] = $_FILES['datafile']['name'][$fileIndex] . " ($invalidLineCount line(s) skipped due to invalid date/time format)";
                }

                // Insert records into the database
                foreach($logs as $employee_id => $employeeLogs){
                    foreach($employeeLogs as $date => $log){
                        // Check if record already exists
                        $check_stmt = $mysqli->prepare("SELECT COUNT(*) FROM biometrics_logs WHERE employee_id = ? AND log_date = ?");
                        $check_stmt->bind_param("ss", $employee_id, $date);
                        $check_stmt->execute();
                        $check_stmt->bind_result($count);
                        $check_stmt->fetch();
                        $check_stmt->close();

                        if($count > 0) {
                            $totalSkipped++;
                            continue; // Skip this record
                        }

                        $time_in = $log['in'];
                        $time_out = $log['out'];

                        // Insert new record
                        $stmt = $mysqli->prepare("INSERT INTO biometrics_logs (employee_id, log_date, time_in, time_out) VALUES (?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param("ssss", $employee_id, $date, $time_in, $time_out);
                            if($stmt->execute()) {
                                $totalInserted++;
                                if($importedMinDate === null || $date < $importedMinDate) {
                                    $importedMinDate = $date;
                                }
                                if($importedMaxDate === null || $date > $importedMaxDate) {
                                    $importedMaxDate = $date;
                                }
                            } else {
                                $errorFiles[] = $_FILES['datafile']['name'][$fileIndex] . " (Insert failed for Employee ID $employee_id on $date)";
                            }
                            $stmt->close();
                        }
                    }
                }
                
            } else {
                $errorFiles[] = $_FILES['datafile']['name'][$fileIndex] . " (Unable to open file)";
            }
        } else {
            $errorFiles[] = $_FILES['datafile']['name'][$fileIndex] . " (Upload error)";
        }
    }

    $message = "Import Summary:\n";
    $message .= "$fileCount files processed\n";
    $message .= "$totalInserted new records inserted\n";
    $message .= "$totalSkipped existing records skipped";

    if($importedMinDate !== null && $importedMaxDate !== null) {
        $message .= "\nImported date range: $importedMinDate to $importedMaxDate";
    } elseif($parsedMinDate !== null && $parsedMaxDate !== null) {
        $message .= "\nFile date range: $parsedMinDate to $parsedMaxDate";
    }
    
    if(!empty($errorFiles)) {
        $message .= "\n\nErrors occurred in the following files:\n" . implode("\n", $errorFiles);
    }

    $redirectUrl = 'index.php?order=desc';
    $redirectMinDate = $importedMinDate ?? $parsedMinDate;
    $redirectMaxDate = $importedMaxDate ?? $parsedMaxDate;
    if($redirectMinDate !== null && $redirectMaxDate !== null) {
        $redirectUrl .= '&search_from_date=' . urlencode($redirectMinDate) . '&search_to_date=' . urlencode($redirectMaxDate);
    }

    $messageType = $totalInserted > 0 ? 'success' : 'warning';
    flashMessage($messageType, 'Import summary', $message);
    header("Location: $redirectUrl");
    exit();
}

include 'header.php';
?>

<?php
if(isset($_SESSION['message'])) {
    echo $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<section class="app-panel import-panel">
    <div class="section-heading">
        <div>
            <span class="section-kicker">Data Import</span>
            <h2>Import Biometrics Data</h2>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="app-form">
        <div class="form-group">
            <label for="datafile">Select DAT or TXT File</label>
            <input type="file" name="datafile[]" id="datafile" class="form-control-file" accept=".dat,.txt" required multiple>
        </div>
        <button type="submit" name="submit" class="btn btn-primary btn-action">Import Files</button>
    </form>
</section>

<footer style="margin-top: auto; text-align: center; padding: 20px;">
    <?php include 'footer.php'; ?>
</footer>
