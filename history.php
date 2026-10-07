<?php

/*
|--------------------------------------------------------------------------
| history.php
|--------------------------------------------------------------------------
| หน้าประวัติการซ่อม
|
| ความสามารถ
| - แสดงประวัติการซ่อม
| - ค้นหาเครื่องจักร
| - กรองสถานะ
| - กรองวันที่
| - แก้ไขข้อมูล
| - ลบข้อมูล
| - ป้องกันข้อมูลซ้ำ
| - หลังบันทึก/ลบ จะกลับมาหน้าประวัติอัตโนมัติ
|--------------------------------------------------------------------------
*/

session_start();

require_once 'db.php';


// ==========================================================
// ตรวจสอบ Login
// ==========================================================

if (!isset($_SESSION['user'])) {

    header('Location: index.php');
    exit;

}

$user = $_SESSION['user'];


// ==========================================================
// ตัวแปร
// ==========================================================

$error = '';

$success = '';

$editRepair = null;


// ==========================================================
// รับข้อความสำเร็จจาก Session
// ==========================================================
//
// ใช้สำหรับแสดงข้อความหลัง Redirect
// เช่น แก้ไขสำเร็จแล้วให้กลับมาหน้านี้
// ==========================================================

if (isset($_SESSION['history_success'])) {

    $success = $_SESSION['history_success'];

    unset($_SESSION['history_success']);

}


// ==========================================================
// รับค่าค้นหา
// ==========================================================

$machineSearch = trim(
    $_GET['machine'] ?? ''
);

$statusSearch = $_GET['status'] ?? '';

$dateFrom = $_GET['date_from'] ?? '';

$dateTo = $_GET['date_to'] ?? '';


// ==========================================================
// ดึงเครื่องจักร
// ==========================================================

$stmt = $db->query("
    SELECT
        id,
        machine_code,
        machine_name
    FROM machines
    ORDER BY machine_code
");

$machines = $stmt->fetchAll();


// ==========================================================
// ดึงรายการปัญหา
// ==========================================================

$stmt = $db->query("
    SELECT
        id,
        problem_name,
        description
    FROM problems
    ORDER BY problem_name
");

$problems = $stmt->fetchAll();


// ==========================================================
// POST
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    $repairId = (int) ($_POST['repair_id'] ?? 0);


    // ======================================================
    // แก้ไขข้อมูล
    // ======================================================

    if (
        $action === 'update' &&
        $repairId > 0
    ) {

        $machineId = (int) (
            $_POST['machine_id'] ?? 0
        );

        $problemId = (int) (
            $_POST['problem_id'] ?? 0
        );

        $reporter = trim(
            $_POST['reporter'] ?? ''
        );

        $status = trim(
            $_POST['status'] ?? ''
        );

        $detail = trim(
            $_POST['detail'] ?? ''
        );

        $solution = trim(
            $_POST['solution'] ?? ''
        );

        $startedAt = trim(
            $_POST['started_at'] ?? ''
        );

        $completedAt = trim(
            $_POST['completed_at'] ?? ''
        );


        // --------------------------------------------------
        // ตรวจสอบข้อมูลเบื้องต้น
        // --------------------------------------------------

        if (
            $machineId <= 0 ||
            $problemId <= 0 ||
            $reporter === '' ||
            $status === '' ||
            $startedAt === ''
        ) {

            $error =
                'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';

        } else {


            // ------------------------------------------------
            // แปลงวันที่จาก datetime-local
            // ------------------------------------------------

            $startedAt = str_replace(
                'T',
                ' ',
                $startedAt
            );

            $completedAt = str_replace(
                'T',
                ' ',
                $completedAt
            );


            if ($completedAt === '') {

                $completedAt = null;

            }


            // ------------------------------------------------
            // ถ้าสถานะเป็นแก้ไขเรียบร้อย
            // แต่ไม่ได้ใส่เวลาเสร็จ
            // ให้ใช้เวลาปัจจุบัน
            // ------------------------------------------------

            if (
                $status === 'แก้ไขเรียบร้อย' &&
                empty($completedAt)
            ) {

                $completedAt =
                    date('Y-m-d H:i:s');

            }


            // ------------------------------------------------
            // ถ้ายังไม่เสร็จ
            // ให้ล้างเวลาเสร็จออก
            // ------------------------------------------------

            if (
                $status !== 'แก้ไขเรียบร้อย'
            ) {

                $completedAt = null;

            }


            try {

                // --------------------------------------------
                // ตรวจสอบข้อมูลซ้ำ
                // --------------------------------------------

                $stmt = $db->prepare("
                    SELECT id
                    FROM repairs
                    WHERE machine_id = ?
                    AND problem_id = ?
                    AND started_at = ?
                    AND id != ?
                ");

                $stmt->execute([

                    $machineId,
                    $problemId,
                    $startedAt,
                    $repairId

                ]);

                $duplicate = $stmt->fetch();


                if ($duplicate) {

                    $error =
                        'ไม่สามารถบันทึกได้ เพราะมีรายการนี้อยู่แล้ว '
                        . '(เครื่องจักร + ปัญหา + เวลาแจ้งซ้ำกัน)';

                } else {


                    // ----------------------------------------
                    // UPDATE
                    // ----------------------------------------

                    $stmt = $db->prepare("
                        UPDATE repairs
                        SET
                            machine_id = ?,
                            problem_id = ?,
                            reporter = ?,
                            status = ?,
                            detail = ?,
                            solution = ?,
                            started_at = ?,
                            completed_at = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");

                    $stmt->execute([

                        $machineId,
                        $problemId,
                        $reporter,
                        $status,
                        $detail,
                        $solution,
                        $startedAt,
                        $completedAt,
                        $repairId

                    ]);


                    // ----------------------------------------
                    // เก็บข้อความสำเร็จไว้ใน Session
                    // ----------------------------------------

                    $_SESSION['history_success'] =
                        'แก้ไขข้อมูลประวัติการซ่อมเรียบร้อยแล้ว';


                    // ----------------------------------------
                    // กลับหน้าประวัติการซ่อมทันที
                    // ----------------------------------------

                    header(
                        'Location: history.php'
                    );

                    exit;

                }

            } catch (PDOException $e) {

                $error =
                    'ไม่สามารถแก้ไขข้อมูลได้: '
                    . $e->getMessage();

            }

        }

    }


    // ======================================================
    // ลบข้อมูล
    // ======================================================

    if (
        $action === 'delete' &&
        $repairId > 0
    ) {

        try {

            // ----------------------------------------------
            // ดึงชื่อรูปก่อนลบ
            // ----------------------------------------------

            $stmt = $db->prepare("
                SELECT image
                FROM repairs
                WHERE id = ?
            ");

            $stmt->execute([
                $repairId
            ]);

            $deleteRepair = $stmt->fetch();


            if (!$deleteRepair) {

                $error =
                    'ไม่พบรายการที่ต้องการลบ';

            } else {


                // ------------------------------------------
                // ลบข้อมูล
                // ------------------------------------------

                $stmt = $db->prepare("
                    DELETE FROM repairs
                    WHERE id = ?
                ");

                $stmt->execute([
                    $repairId
                ]);


                // ------------------------------------------
                // ลบรูปภาพถ้ามี
                // ------------------------------------------

                if (
                    !empty(
                        $deleteRepair['image']
                    )
                ) {

                    $imageFile =
                        __DIR__ . '/' .
                        $deleteRepair['image'];


                    if (
                        file_exists($imageFile)
                    ) {

                        unlink($imageFile);

                    }

                }


                // ------------------------------------------
                // เก็บข้อความสำเร็จ
                // ------------------------------------------

                $_SESSION['history_success'] =
                    'ลบรายการเรียบร้อยแล้ว';


                // ------------------------------------------
                // กลับหน้าประวัติ
                // ------------------------------------------

                header(
                    'Location: history.php'
                );

                exit;

            }

        } catch (PDOException $e) {

            $error =
                'ไม่สามารถลบข้อมูลได้: '
                . $e->getMessage();

        }

    }

}


// ==========================================================
// เปิดโหมดแก้ไข
// ==========================================================

if (
    isset($_GET['edit']) &&
    (int) $_GET['edit'] > 0
) {

    $editId = (int) $_GET['edit'];


    $stmt = $db->prepare("
        SELECT *
        FROM repairs
        WHERE id = ?
    ");

    $stmt->execute([
        $editId
    ]);

    $editRepair = $stmt->fetch();


    if (!$editRepair) {

        $error =
            'ไม่พบข้อมูลที่ต้องการแก้ไข';

    }

}


// ==========================================================
// สร้าง SQL สำหรับค้นหา
// ==========================================================

$sql = "
    SELECT

        repairs.id,

        repairs.machine_id,
        repairs.problem_id,

        repairs.reporter,
        repairs.status,

        repairs.detail,
        repairs.solution,

        repairs.image,

        repairs.started_at,
        repairs.completed_at,

        repairs.created_at,
        repairs.updated_at,

        machines.machine_code,
        machines.machine_name,

        problems.problem_name

    FROM repairs

    INNER JOIN machines
        ON repairs.machine_id = machines.id

    INNER JOIN problems
        ON repairs.problem_id = problems.id

    WHERE 1 = 1
";


$params = [];


// ==========================================================
// ค้นหาเครื่องจักร
// ==========================================================

if ($machineSearch !== '') {

    $sql .= "
        AND (
            machines.machine_code LIKE ?
            OR machines.machine_name LIKE ?
        )
    ";

    $searchText =
        '%' . $machineSearch . '%';

    $params[] = $searchText;

    $params[] = $searchText;

}


// ==========================================================
// กรองสถานะ
// ==========================================================

if ($statusSearch !== '') {

    $sql .= "
        AND repairs.status = ?
    ";

    $params[] = $statusSearch;

}


// ==========================================================
// วันที่เริ่ม
// ==========================================================

if ($dateFrom !== '') {

    $sql .= "
        AND date(repairs.started_at)
        >= date(?)
    ";

    $params[] = $dateFrom;

}


// ==========================================================
// วันที่สิ้นสุด
// ==========================================================

if ($dateTo !== '') {

    $sql .= "
        AND date(repairs.started_at)
        <= date(?)
    ";

    $params[] = $dateTo;

}


// ==========================================================
// เรียงข้อมูล
// ==========================================================

$sql .= "
    ORDER BY repairs.started_at DESC
";


$stmt = $db->prepare($sql);

$stmt->execute($params);

$repairs = $stmt->fetchAll();

$totalResults = count($repairs);


// ==========================================================
// ฟังก์ชันแปลง datetime สำหรับ input
// ==========================================================

function datetimeForInput($value)
{

    if (empty($value)) {

        return '';

    }

    return date(
        'Y-m-d\TH:i',
        strtotime($value)
    );

}

?>

<!DOCTYPE html>

<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        ประวัติการซ่อม - Machine Stop Monitor
    </title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <style>

        /* ==================================================
           ส่วนแก้ไขประวัติ
           ================================================== */

        .edit-card {

            background: white;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 20px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);

        }


        .edit-card h2 {

            margin-top: 0;

            color: #172b4d;

        }


        .edit-form {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 16px;

        }


        .edit-form .full {

            grid-column: span 2;

        }


        .edit-group label {

            display: block;

            margin-bottom: 7px;

            font-weight: bold;

            font-size: 13px;

            color: #475569;

        }


        .edit-group input,
        .edit-group select,
        .edit-group textarea {

            width: 100%;

            box-sizing: border-box;

            padding: 11px;

            border: 1px solid #cbd5e1;

            border-radius: 7px;

            font-family: inherit;

            font-size: 14px;

            outline: none;

        }


        .edit-group textarea {

            min-height: 100px;

            resize: vertical;

        }


        .edit-group input:focus,
        .edit-group select:focus,
        .edit-group textarea:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.1);

        }


        .edit-buttons {

            grid-column: span 2;

            display: flex;

            gap: 10px;

            margin-top: 5px;

        }


        .save-edit-btn,
        .cancel-edit-btn {

            padding: 11px 20px;

            border: none;

            border-radius: 7px;

            text-decoration: none;

            font-family: inherit;

            font-weight: bold;

            cursor: pointer;

        }


        .save-edit-btn {

            background: #2563eb;

            color: white;

        }


        .save-edit-btn:hover {

            background: #1d4ed8;

        }


        .cancel-edit-btn {

            background: #e2e8f0;

            color: #334155;

        }


        .cancel-edit-btn:hover {

            background: #cbd5e1;

        }


        /* ==================================================
           Responsive
           ================================================== */

        @media (max-width: 700px) {

            .edit-form {

                grid-template-columns: 1fr;

            }


            .edit-form .full {

                grid-column: auto;

            }


            .edit-buttons {

                grid-column: auto;

                flex-direction: column;

            }

        }

    </style>

</head>


<body class="dashboard-page">


<!-- ==========================================================
     Header
========================================================== -->

<header class="dashboard-header">

    <div>

        <a
            href="dashboard.php"
            class="site-logo"
        >
            Machine Stop Monitor
        </a>

        <p>
            ประวัติการซ่อม
        </p>

    </div>


    <div class="user-area">

        <span>

            👤

            <?php

            echo htmlspecialchars(
                $user['full_name']
            );

            ?>

        </span>


        <a
            href="dashboard.php"
            class="back-btn"
        >
            ← Dashboard
        </a>


        <a
            href="logout.php"
            class="logout-btn"
        >
            ออกจากระบบ
        </a>

    </div>

</header>


<!-- ==========================================================
     Main
========================================================== -->

<main class="history-container">


    <!-- ======================================================
         แจ้งเตือน
    ======================================================= -->

    <?php if ($success !== ''): ?>

        <div class="history-success">

            ✅

            <?php

            echo htmlspecialchars(
                $success
            );

            ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="history-error">

            ⚠️

            <?php

            echo htmlspecialchars(
                $error
            );

            ?>

        </div>

    <?php endif; ?>


    <!-- ======================================================
         ฟอร์มแก้ไข
    ======================================================= -->

    <?php if ($editRepair): ?>

        <section class="edit-card">

            <h2>
                ✏️ แก้ไขประวัติการซ่อม
            </h2>


            <p>
                แก้ไขข้อมูลรายการ
                #<?php echo $editRepair['id']; ?>
            </p>


            <form
                method="POST"
                class="edit-form"
            >

                <input
                    type="hidden"
                    name="action"
                    value="update"
                >


                <input
                    type="hidden"
                    name="repair_id"
                    value="<?php
                        echo $editRepair['id'];
                    ?>"
                >


                <!-- เครื่องจักร -->

                <div class="edit-group">

                    <label>
                        เครื่องจักร *
                    </label>


                    <select
                        name="machine_id"
                        required
                    >

                        <option value="">
                            -- เลือกเครื่องจักร --
                        </option>


                        <?php foreach (
                            $machines as $machine
                        ): ?>

                            <option
                                value="<?php
                                    echo $machine['id'];
                                ?>"
                                <?php

                                echo (
                                    $editRepair['machine_id']
                                    == $machine['id']
                                )
                                ? 'selected'
                                : '';

                                ?>
                            >

                                <?php

                                echo htmlspecialchars(
                                    $machine['machine_code']
                                );

                                ?>

                                -

                                <?php

                                echo htmlspecialchars(
                                    $machine['machine_name']
                                );

                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- ปัญหา -->

                <div class="edit-group">

                    <label>
                        ปัญหา *
                    </label>


                    <select
                        name="problem_id"
                        required
                    >

                        <option value="">
                            -- เลือกปัญหา --
                        </option>


                        <?php foreach (
                            $problems as $problem
                        ): ?>

                            <option
                                value="<?php
                                    echo $problem['id'];
                                ?>"
                                <?php

                                echo (
                                    $editRepair['problem_id']
                                    == $problem['id']
                                )
                                ? 'selected'
                                : '';

                                ?>
                            >

                                <?php

                                echo htmlspecialchars(
                                    $problem['problem_name']
                                );

                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- ผู้แจ้ง -->

                <div class="edit-group">

                    <label>
                        ผู้แจ้ง *
                    </label>


                    <input
                        type="text"
                        name="reporter"
                        value="<?php
                            echo htmlspecialchars(
                                $editRepair['reporter']
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- สถานะ -->

                <div class="edit-group">

                    <label>
                        สถานะ *
                    </label>


                    <select
                        name="status"
                        required
                    >

                        <option
                            value="แจ้งเตือน"
                            <?php

                            echo $editRepair['status']
                                === 'แจ้งเตือน'
                                ? 'selected'
                                : '';

                            ?>
                        >
                            แจ้งเตือน
                        </option>


                        <option
                            value="กำลังแก้ไข"
                            <?php

                            echo $editRepair['status']
                                === 'กำลังแก้ไข'
                                ? 'selected'
                                : '';

                            ?>
                        >
                            กำลังแก้ไข
                        </option>


                        <option
                            value="แก้ไขเรียบร้อย"
                            <?php

                            echo $editRepair['status']
                                === 'แก้ไขเรียบร้อย'
                                ? 'selected'
                                : '';

                            ?>
                        >
                            แก้ไขเรียบร้อย
                        </option>

                    </select>

                </div>


                <!-- เวลาแจ้ง -->

                <div class="edit-group">

                    <label>
                        เวลาแจ้ง *
                    </label>


                    <input
                        type="datetime-local"
                        name="started_at"
                        value="<?php

                            echo datetimeForInput(
                                $editRepair['started_at']
                            );

                        ?>"
                        required
                    >

                </div>


                <!-- เวลาเสร็จ -->

                <div class="edit-group">

                    <label>
                        เวลาแก้ไขเสร็จ
                    </label>


                    <input
                        type="datetime-local"
                        name="completed_at"
                        value="<?php

                            echo datetimeForInput(
                                $editRepair['completed_at']
                            );

                        ?>"
                    >

                </div>


                <!-- รายละเอียด -->

                <div class="edit-group full">

                    <label>
                        รายละเอียดปัญหา
                    </label>


                    <textarea
                        name="detail"
                        placeholder="รายละเอียดของปัญหา"
                    ><?php

                        echo htmlspecialchars(
                            $editRepair['detail'] ?? ''
                        );

                    ?></textarea>

                </div>


                <!-- วิธีแก้ไข -->

                <div class="edit-group full">

                    <label>
                        วิธีการแก้ไข
                    </label>


                    <textarea
                        name="solution"
                        placeholder="รายละเอียดวิธีการแก้ไข"
                    ><?php

                        echo htmlspecialchars(
                            $editRepair['solution'] ?? ''
                        );

                    ?></textarea>

                </div>


                <!-- ปุ่ม -->

                <div class="edit-buttons">

                    <button
                        type="submit"
                        class="save-edit-btn"
                    >
                        💾 บันทึกการแก้ไข
                    </button>


                    <a
                        href="history.php"
                        class="cancel-edit-btn"
                    >
                        ยกเลิก
                    </a>

                </div>


            </form>

        </section>

    <?php endif; ?>


    <!-- ======================================================
         Filter
    ======================================================= -->

    <section class="history-filter">

        <div class="history-title">

            <div>

                <h2>
                    📋 ประวัติการซ่อม
                </h2>

                <p>
                    ค้นหาและตรวจสอบรายการซ่อมทั้งหมด
                </p>

            </div>


            <strong class="result-count">

                พบ
                <?php echo $totalResults; ?>
                รายการ

            </strong>

        </div>


        <form
            method="GET"
            class="filter-form"
        >

            <div class="filter-group">

                <label>
                    เครื่องจักร
                </label>


                <input
                    type="text"
                    name="machine"
                    value="<?php

                        echo htmlspecialchars(
                            $machineSearch
                        );

                    ?>"
                    placeholder="เช่น MC-001"
                >

            </div>


            <div class="filter-group">

                <label>
                    สถานะ
                </label>


                <select name="status">

                    <option value="">
                        ทุกสถานะ
                    </option>


                    <option
                        value="แจ้งเตือน"
                        <?php

                        echo $statusSearch === 'แจ้งเตือน'
                            ? 'selected'
                            : '';

                        ?>
                    >
                        แจ้งเตือน
                    </option>


                    <option
                        value="กำลังแก้ไข"
                        <?php

                        echo $statusSearch === 'กำลังแก้ไข'
                            ? 'selected'
                            : '';

                        ?>
                    >
                        กำลังแก้ไข
                    </option>


                    <option
                        value="แก้ไขเรียบร้อย"
                        <?php

                        echo $statusSearch === 'แก้ไขเรียบร้อย'
                            ? 'selected'
                            : '';

                        ?>
                    >
                        แก้ไขเรียบร้อย
                    </option>

                </select>

            </div>


            <div class="filter-group">

                <label>
                    ตั้งแต่วันที่
                </label>


                <input
                    type="date"
                    name="date_from"
                    value="<?php

                        echo htmlspecialchars(
                            $dateFrom
                        );

                    ?>"
                >

            </div>


            <div class="filter-group">

                <label>
                    ถึงวันที่
                </label>


                <input
                    type="date"
                    name="date_to"
                    value="<?php

                        echo htmlspecialchars(
                            $dateTo
                        );

                    ?>"
                >

            </div>


            <div class="filter-buttons">

                <button
                    type="submit"
                    class="search-btn"
                >
                    🔎 ค้นหา
                </button>


                <a
                    href="history.php"
                    class="reset-btn"
                >
                    รีเซ็ต
                </a>

            </div>

        </form>

    </section>


    <!-- ======================================================
         ตาราง
    ======================================================= -->

    <section class="history-table-card">


        <?php if ($totalResults > 0): ?>


            <div class="history-table-wrapper">

                <table class="history-table">

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                เครื่องจักร
                            </th>

                            <th>
                                ปัญหา
                            </th>

                            <th>
                                ผู้แจ้ง
                            </th>

                            <th>
                                สถานะ
                            </th>

                            <th>
                                เวลาแจ้ง
                            </th>

                            <th>
                                เวลาเสร็จ
                            </th>

                            <th>
                                จัดการ
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $repairs as $repair
                    ): ?>


                        <tr>

                            <!-- ID -->

                            <td>

                                <?php
                                echo $repair['id'];
                                ?>

                            </td>


                            <!-- เครื่องจักร -->

                            <td>

                                <strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $repair['machine_code']
                                    );

                                    ?>

                                </strong>


                                <small>

                                    <?php

                                    echo htmlspecialchars(
                                        $repair['machine_name']
                                    );

                                    ?>

                                </small>

                            </td>


                            <!-- ปัญหา -->

                            <td>

                                <strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $repair['problem_name']
                                    );

                                    ?>

                                </strong>


                                <?php if (
                                    !empty(
                                        $repair['detail']
                                    )
                                ): ?>

                                    <small>

                                        <?php

                                        echo htmlspecialchars(
                                            $repair['detail']
                                        );

                                        ?>

                                    </small>

                                <?php endif; ?>

                            </td>


                            <!-- ผู้แจ้ง -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $repair['reporter']
                                );

                                ?>

                            </td>


                            <!-- สถานะ -->

                            <td>

                                <?php

                                $statusClass = '';

                                if (
                                    $repair['status']
                                    === 'แจ้งเตือน'
                                ) {

                                    $statusClass =
                                        'status-alert';

                                } elseif (
                                    $repair['status']
                                    === 'กำลังแก้ไข'
                                ) {

                                    $statusClass =
                                        'status-repairing';

                                } elseif (
                                    $repair['status']
                                    === 'แก้ไขเรียบร้อย'
                                ) {

                                    $statusClass =
                                        'status-completed';

                                }

                                ?>


                                <span
                                    class="status-badge <?php
                                        echo $statusClass;
                                    ?>"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $repair['status']
                                    );

                                    ?>

                                </span>

                            </td>


                            <!-- เวลาแจ้ง -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $repair['started_at']
                                );

                                ?>

                            </td>


                            <!-- เวลาเสร็จ -->

                            <td>

                                <?php

                                if (
                                    !empty(
                                        $repair['completed_at']
                                    )
                                ) {

                                    echo htmlspecialchars(
                                        $repair['completed_at']
                                    );

                                } else {

                                    echo '-';

                                }

                                ?>

                            </td>


                            <!-- จัดการ -->

                            <td>

                                <div class="history-actions">


                                    <!-- ดูรายละเอียด -->

                                    <a
                                        href="repair_detail.php?id=<?php
                                            echo $repair['id'];
                                        ?>"
                                        class="view-btn"
                                    >
                                        👁️ ดู
                                    </a>


                                    <!-- แก้ไข -->

                                    <a
                                        href="history.php?edit=<?php
                                            echo $repair['id'];
                                        ?>"
                                        class="edit-btn"
                                    >
                                        ✏️ แก้ไข
                                    </a>


                                    <!-- ลบ -->

                                    <form
                                        method="POST"
                                        onsubmit="return confirm(
                                            'ต้องการลบรายการนี้หรือไม่?'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >


                                        <input
                                            type="hidden"
                                            name="repair_id"
                                            value="<?php
                                                echo $repair['id'];
                                            ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="delete-btn"
                                        >
                                            🗑️ ลบ
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="history-empty">

                <div>
                    🔍
                </div>


                <h3>
                    ไม่พบข้อมูล
                </h3>


                <p>
                    ไม่พบรายการตามเงื่อนไขที่ค้นหา
                </p>

            </div>


        <?php endif; ?>


    </section>


</main>


</body>

</html>