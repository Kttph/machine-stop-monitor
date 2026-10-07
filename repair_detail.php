<?php

/*
|--------------------------------------------------------------------------
| repair_detail.php
|--------------------------------------------------------------------------
| หน้าแสดงรายละเอียดงานซ่อม
|
| การทำงาน
| 1. แสดงรายละเอียดงานซ่อม
| 2. ถ้าสถานะ "แจ้งเตือน" สามารถกดเริ่มแก้ไข
| 3. เปลี่ยนเป็น "กำลังแก้ไข"
| 4. กรอกวิธีการแก้ไข
| 5. กดปิดงาน
| 6. เปลี่ยนเป็น "แก้ไขเรียบร้อย"
| 7. บันทึกเวลาแก้ไขเสร็จ
| 8. กลับหน้าประวัติการซ่อม
| 9. แสดงรูปภาพ
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
// รับ ID รายการซ่อม
// ==========================================================

$repairId = (int) ($_GET['id'] ?? 0);


// ==========================================================
// ตรวจสอบ ID
// ==========================================================

if ($repairId <= 0) {

    header('Location: history.php');
    exit;

}


// ==========================================================
// ฟังก์ชันแปลงวันที่
// ==========================================================

function formatThaiDateTime($datetime)
{

    if (empty($datetime)) {

        return '-';

    }

    $timestamp = strtotime($datetime);

    if (!$timestamp) {

        return $datetime;

    }

    return date(
        'd/m/Y H:i',
        $timestamp
    );

}


// ==========================================================
// จัดการ POST
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    // ======================================================
    // เริ่มแก้ไข
    // ======================================================

    if ($action === 'start_repair') {

        $stmt = $db->prepare("
            UPDATE repairs

            SET
                status = 'กำลังแก้ไข',
                updated_at = CURRENT_TIMESTAMP

            WHERE id = ?
            AND status = 'แจ้งเตือน'
        ");

        $stmt->execute([
            $repairId
        ]);


        // กลับมาหน้ารายละเอียด
        // เพื่อแสดงสถานะ "กำลังแก้ไข"

        header(
            'Location: repair_detail.php?id=' . $repairId
        );

        exit;

    }


    // ======================================================
    // ปิดงาน
    // ======================================================

    if ($action === 'complete_repair') {

        $solution = trim(
            $_POST['solution'] ?? ''
        );


        // --------------------------------------------------
        // ต้องกรอกวิธีแก้ไข
        // --------------------------------------------------

        if ($solution === '') {

            $_SESSION['repair_error'] =
                'กรุณาระบุวิธีการแก้ไขก่อนปิดงาน';

            header(
                'Location: repair_detail.php?id=' . $repairId
            );

            exit;

        }


        // --------------------------------------------------
        // เปลี่ยนสถานะเป็นแก้ไขเรียบร้อย
        // และบันทึกเวลาเสร็จ
        // --------------------------------------------------

        $stmt = $db->prepare("
            UPDATE repairs

            SET
                status = 'แก้ไขเรียบร้อย',
                solution = ?,
                completed_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = ?
            AND status = 'กำลังแก้ไข'
        ");

        $stmt->execute([
            $solution,
            $repairId
        ]);


        // --------------------------------------------------
        // ตรวจสอบว่าปิดงานสำเร็จหรือไม่
        // --------------------------------------------------

        if ($stmt->rowCount() > 0) {

            // บันทึกข้อความสำเร็จ

            $_SESSION['history_success'] =
                'ปิดงานซ่อมเรียบร้อยแล้ว';


            // ------------------------------------------------
            // หลังปิดงาน
            // กลับหน้าประวัติทันที
            // ------------------------------------------------

            header(
                'Location: history.php'
            );

            exit;

        } else {

            // ถ้าไม่สามารถปิดงานได้
            // เช่น สถานะไม่ใช่ "กำลังแก้ไข"

            $_SESSION['repair_error'] =
                'ไม่สามารถปิดงานได้ กรุณาตรวจสอบสถานะงานอีกครั้ง';

            header(
                'Location: repair_detail.php?id=' . $repairId
            );

            exit;

        }

    }

}


// ==========================================================
// ดึงข้อมูลรายการซ่อม
// ==========================================================

$stmt = $db->prepare("
    SELECT

        repairs.*,

        machines.machine_code,
        machines.machine_name,

        problems.problem_name,
        problems.description AS problem_description,

        users.full_name AS creator_name

    FROM repairs

    INNER JOIN machines
        ON repairs.machine_id = machines.id

    INNER JOIN problems
        ON repairs.problem_id = problems.id

    LEFT JOIN users
        ON repairs.created_by = users.id

    WHERE repairs.id = ?

    LIMIT 1
");


$stmt->execute([
    $repairId
]);


$repair = $stmt->fetch();


// ==========================================================
// ไม่พบข้อมูล
// ==========================================================

if (!$repair) {

    die('ไม่พบรายการซ่อมที่ต้องการ');

}


// ==========================================================
// ข้อความ Error
// ==========================================================

$errorMessage =
    $_SESSION['repair_error'] ?? '';

unset(
    $_SESSION['repair_error']
);


// ==========================================================
// กำหนดสีสถานะ
// ==========================================================

$statusClass = '';

if ($repair['status'] === 'แจ้งเตือน') {

    $statusClass = 'detail-alert';

} elseif ($repair['status'] === 'กำลังแก้ไข') {

    $statusClass = 'detail-repairing';

} elseif ($repair['status'] === 'แก้ไขเรียบร้อย') {

    $statusClass = 'detail-completed';

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
        รายละเอียดการซ่อม #<?php echo $repair['id']; ?>
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <style>

        /* ==================================================
           หน้ารายละเอียด
           ================================================== */

        .detail-container {

            max-width: 1100px;

            margin: 30px auto;

            padding: 0 20px;

        }


        /* ==================================================
           Header
           ================================================== */

        .detail-header {

            background: white;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 20px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

        }


        .detail-header h2 {

            margin: 0;

            color: #172b4d;

        }


        .detail-header p {

            margin: 6px 0 0;

            color: #64748b;

            font-size: 14px;

        }


        /* ==================================================
           ปุ่ม
           ================================================== */

        .detail-buttons {

            display: flex;

            gap: 8px;

            flex-wrap: wrap;

        }


        .detail-back-btn,
        .detail-edit-btn {

            display: inline-block;

            padding: 10px 15px;

            border-radius: 7px;

            text-decoration: none;

            font-size: 13px;

            font-weight: bold;

        }


        .detail-back-btn {

            background: #e2e8f0;

            color: #334155;

        }


        .detail-back-btn:hover {

            background: #cbd5e1;

        }


        .detail-edit-btn {

            background: #2563eb;

            color: white;

        }


        .detail-edit-btn:hover {

            background: #1d4ed8;

        }


        /* ==================================================
           สถานะ
           ================================================== */

        .detail-status {

            display: inline-block;

            padding: 9px 15px;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;

            margin-top: 12px;

        }


        .detail-alert {

            background: #fee2e2;

            color: #b91c1c;

        }


        .detail-repairing {

            background: #fef3c7;

            color: #92400e;

        }


        .detail-completed {

            background: #dcfce7;

            color: #166534;

        }


        /* ==================================================
           Card
           ================================================== */

        .detail-card {

            background: white;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 20px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);

        }


        .detail-card h3 {

            margin-top: 0;

            margin-bottom: 20px;

            color: #172b4d;

            border-bottom: 1px solid #e5e7eb;

            padding-bottom: 12px;

        }


        /* ==================================================
           Grid
           ================================================== */

        .detail-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;

        }


        .detail-item {

            background: #f8fafc;

            border-radius: 8px;

            padding: 14px;

        }


        .detail-label {

            display: block;

            color: #64748b;

            font-size: 12px;

            margin-bottom: 5px;

        }


        .detail-value {

            color: #1e293b;

            font-size: 15px;

            font-weight: 600;

        }


        /* ==================================================
           รายละเอียดข้อความ
           ================================================== */

        .detail-text {

            background: #f8fafc;

            border-radius: 8px;

            padding: 18px;

            color: #334155;

            line-height: 1.7;

            white-space: pre-wrap;

            min-height: 50px;

        }


        /* ==================================================
           กล่องจัดการสถานะ
           ================================================== */

        .action-card {

            border: 2px solid #e2e8f0;

        }


        .action-alert {

            border-color: #fecaca;

            background: #fffafa;

        }


        .action-repairing {

            border-color: #fde68a;

            background: #fffdf5;

        }


        .action-completed {

            border-color: #bbf7d0;

            background: #f7fff9;

        }


        .action-description {

            color: #64748b;

            margin-bottom: 18px;

            line-height: 1.7;

        }


        /* ==================================================
           ปุ่มเริ่มแก้ไข
           ================================================== */

        .start-repair-btn {

            border: none;

            background: #f59e0b;

            color: white;

            padding: 13px 22px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

        }


        .start-repair-btn:hover {

            background: #d97706;

        }


        /* ==================================================
           ปุ่มปิดงาน
           ================================================== */

        .complete-repair-btn {

            border: none;

            background: #16a34a;

            color: white;

            padding: 13px 22px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

        }


        .complete-repair-btn:hover {

            background: #15803d;

        }


        /* ==================================================
           ช่องวิธีแก้ไข
           ================================================== */

        .solution-label {

            display: block;

            font-weight: bold;

            color: #334155;

            margin-bottom: 8px;

        }


        .solution-textarea {

            width: 100%;

            min-height: 120px;

            box-sizing: border-box;

            padding: 12px;

            border: 1px solid #cbd5e1;

            border-radius: 8px;

            font-family: inherit;

            font-size: 14px;

            resize: vertical;

            margin-bottom: 15px;

        }


        .solution-textarea:focus {

            outline: none;

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.12);

        }


        /* ==================================================
           Error
           ================================================== */

        .detail-error {

            background: #fee2e2;

            color: #b91c1c;

            padding: 13px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-weight: bold;

        }


        /* ==================================================
           รูปภาพ
           ================================================== */

        .repair-image-wrapper {

            text-align: center;

        }


        .repair-image {

            max-width: 100%;

            max-height: 600px;

            border-radius: 10px;

            border: 1px solid #e2e8f0;

            box-shadow:
                0 4px 12px
                rgba(0, 0, 0, 0.08);

        }


        .no-image {

            background: #f8fafc;

            color: #94a3b8;

            padding: 40px;

            text-align: center;

            border-radius: 10px;

        }


        /* ==================================================
           Responsive
           ================================================== */

        @media (max-width: 700px) {

            .detail-container {

                padding: 0 12px;

                margin-top: 20px;

            }


            .detail-header {

                flex-direction: column;

                align-items: flex-start;

            }


            .detail-buttons {

                width: 100%;

            }


            .detail-back-btn,
            .detail-edit-btn {

                flex: 1;

                text-align: center;

            }


            .detail-grid {

                grid-template-columns: 1fr;

            }


            .detail-card {

                padding: 18px;

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

        <a href="dashboard.php" class="site-logo">
            Machine Stop Monitor
        </a>

        <p>
            รายละเอียดการซ่อม
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

<main class="detail-container">


    <!-- ======================================================
         หัวข้อ
    ======================================================= -->

    <section class="detail-header">

        <div>

            <h2>

                🔧 รายละเอียดงานซ่อม
                #<?php echo $repair['id']; ?>

            </h2>


            <p>

                <?php

                echo htmlspecialchars(
                    $repair['machine_code']
                );

                ?>

                -

                <?php

                echo htmlspecialchars(
                    $repair['machine_name']
                );

                ?>

            </p>


            <span
                class="detail-status <?php
                    echo $statusClass;
                ?>"
            >

                <?php

                echo htmlspecialchars(
                    $repair['status']
                );

                ?>

            </span>

        </div>


        <div class="detail-buttons">

            <a
                href="history.php"
                class="detail-back-btn"
            >
                ← กลับประวัติ
            </a>


            <a
                href="history.php?edit=<?php
                    echo $repair['id'];
                ?>"
                class="detail-edit-btn"
            >
                ✏️ แก้ไข
            </a>

        </div>

    </section>


    <!-- ======================================================
         Error
    ======================================================= -->

    <?php if ($errorMessage !== ''): ?>

        <div class="detail-error">

            ⚠️

            <?php

            echo htmlspecialchars(
                $errorMessage
            );

            ?>

        </div>

    <?php endif; ?>


    <!-- ======================================================
         สถานะ = แจ้งเตือน
    ======================================================= -->

    <?php if ($repair['status'] === 'แจ้งเตือน'): ?>

        <section
            class="detail-card action-card action-alert"
        >

            <h3>
                🔴 งานรอดำเนินการ
            </h3>


            <p class="action-description">

                งานนี้ถูกแจ้งเข้ามาแล้ว
                หากช่างพร้อมดำเนินการ
                ให้กดปุ่ม "เริ่มแก้ไข"

            </p>


            <form
                method="POST"
                onsubmit="
                    return confirm(
                        'ยืนยันเริ่มแก้ไขงานนี้หรือไม่?'
                    );
                "
            >

                <input
                    type="hidden"
                    name="action"
                    value="start_repair"
                >


                <button
                    type="submit"
                    class="start-repair-btn"
                >
                    🔧 เริ่มแก้ไข
                </button>

            </form>

        </section>


    <!-- ======================================================
         สถานะ = กำลังแก้ไข
    ======================================================= -->

    <?php elseif (
        $repair['status'] === 'กำลังแก้ไข'
    ): ?>

        <section
            class="detail-card action-card action-repairing"
        >

            <h3>
                🟡 กำลังดำเนินการซ่อม
            </h3>


            <p class="action-description">

                งานนี้อยู่ระหว่างการแก้ไข
                เมื่อซ่อมเสร็จแล้ว
                กรุณาระบุวิธีการแก้ไข
                แล้วกด "ปิดงาน"

            </p>


            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="complete_repair"
                >


                <label
                    for="solution"
                    class="solution-label"
                >

                    🔧 วิธีการแก้ไข

                    <span style="color:red;">
                        *
                    </span>

                </label>


                <textarea
                    name="solution"
                    id="solution"
                    class="solution-textarea"
                    placeholder="ระบุวิธีการแก้ไขปัญหา..."
                    required
                ><?php

                    echo htmlspecialchars(
                        $repair['solution'] ?? ''
                    );

                ?></textarea>


                <button
                    type="submit"
                    class="complete-repair-btn"
                    onclick="
                        return confirm(
                            'ยืนยันว่าซ่อมเสร็จเรียบร้อยแล้วหรือไม่?'
                        );
                    "
                >
                    ✅ ปิดงาน
                </button>

            </form>

        </section>


    <!-- ======================================================
         สถานะ = แก้ไขเรียบร้อย
    ======================================================= -->

    <?php elseif (
        $repair['status'] === 'แก้ไขเรียบร้อย'
    ): ?>

        <section
            class="detail-card action-card action-completed"
        >

            <h3>
                🟢 งานเสร็จเรียบร้อย
            </h3>


            <p class="action-description">

                งานนี้ได้รับการแก้ไขและปิดงานเรียบร้อยแล้ว

            </p>

        </section>

    <?php endif; ?>


    <!-- ======================================================
         ข้อมูลทั่วไป
    ======================================================= -->

    <section class="detail-card">

        <h3>
            🏭 ข้อมูลการแจ้งปัญหา
        </h3>


        <div class="detail-grid">


            <div class="detail-item">

                <span class="detail-label">
                    เครื่องจักร
                </span>


                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $repair['machine_code']
                    );

                    ?>

                    -

                    <?php

                    echo htmlspecialchars(
                        $repair['machine_name']
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    ปัญหา
                </span>


                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $repair['problem_name']
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    ผู้แจ้ง
                </span>


                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $repair['reporter']
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    เวลาแจ้ง
                </span>


                <span class="detail-value">

                    <?php

                    echo formatThaiDateTime(
                        $repair['started_at']
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    เวลาแก้ไขเสร็จ
                </span>


                <span class="detail-value">

                    <?php

                    echo formatThaiDateTime(
                        $repair['completed_at']
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    ผู้บันทึก
                </span>


                <span class="detail-value">

                    <?php

                    if (
                        !empty(
                            $repair['creator_name']
                        )
                    ) {

                        echo htmlspecialchars(
                            $repair['creator_name']
                        );

                    } else {

                        echo '-';

                    }

                    ?>

                </span>

            </div>


        </div>

    </section>


    <!-- ======================================================
         รายละเอียดปัญหา
    ======================================================= -->

    <section class="detail-card">

        <h3>
            ⚠️ รายละเอียดปัญหา
        </h3>


        <div class="detail-text">

            <?php

            if (
                !empty(
                    $repair['detail']
                )
            ) {

                echo htmlspecialchars(
                    $repair['detail']
                );

            } elseif (
                !empty(
                    $repair['problem_description']
                )
            ) {

                echo htmlspecialchars(
                    $repair['problem_description']
                );

            } else {

                echo 'ไม่มีรายละเอียด';

            }

            ?>

        </div>

    </section>


    <!-- ======================================================
         วิธีแก้ไข
    ======================================================= -->

    <section class="detail-card">

        <h3>
            🔧 วิธีการแก้ไข
        </h3>


        <div class="detail-text">

            <?php

            if (
                !empty(
                    $repair['solution']
                )
            ) {

                echo htmlspecialchars(
                    $repair['solution']
                );

            } else {

                echo 'ยังไม่มีข้อมูลการแก้ไข';

            }

            ?>

        </div>

    </section>


    <!-- ======================================================
         รูปภาพ
    ======================================================= -->

    <section class="detail-card">

        <h3>
            📷 รูปภาพเครื่องจักร / ปัญหา
        </h3>


        <?php

        $imagePath = '';

        if (
            !empty(
                $repair['image']
            )
        ) {

            $imagePath =
                $repair['image'];

        }

        ?>


        <?php if ($imagePath !== ''): ?>

            <?php

            $fullImagePath =
                __DIR__ . '/' . $imagePath;

            ?>


            <?php if (
                file_exists(
                    $fullImagePath
                )
            ): ?>

                <div class="repair-image-wrapper">

                    <img
                        src="<?php

                            echo htmlspecialchars(
                                $imagePath
                            );

                        ?>"
                        alt="รูปภาพงานซ่อม"
                        class="repair-image"
                    >

                </div>


            <?php else: ?>

                <div class="no-image">

                    ⚠️

                    ไม่พบไฟล์รูปภาพ

                </div>

            <?php endif; ?>


        <?php else: ?>

            <div class="no-image">

                📷

                รายการนี้ไม่มีรูปภาพ

            </div>

        <?php endif; ?>


    </section>


    <!-- ======================================================
         ข้อมูลระบบ
    ======================================================= -->

    <section class="detail-card">

        <h3>
            ℹ️ ข้อมูลระบบ
        </h3>


        <div class="detail-grid">


            <div class="detail-item">

                <span class="detail-label">
                    สร้างรายการเมื่อ
                </span>


                <span class="detail-value">

                    <?php

                    echo formatThaiDateTime(
                        $repair['created_at']
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    แก้ไขล่าสุด
                </span>


                <span class="detail-value">

                    <?php

                    echo formatThaiDateTime(
                        $repair['updated_at']
                    );

                    ?>

                </span>

            </div>


        </div>

    </section>


</main>


</body>

</html>