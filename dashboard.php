<?php

/*
|--------------------------------------------------------------------------
| dashboard.php
|--------------------------------------------------------------------------
| หน้าหลักของระบบหลังจาก Login สำเร็จ
|
| หน้านี้จะแสดง:
| - จำนวนรายการแจ้งเตือน
| - จำนวนรายการกำลังแก้ไข
| - จำนวนรายการแก้ไขเรียบร้อย
| - รายการปัญหาเครื่องจักรล่าสุด
|--------------------------------------------------------------------------
*/

// เริ่มใช้งาน Session
session_start();

// เรียกไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db.php';

// ----------------------------------------------------------
// ตรวจสอบว่าผู้ใช้ Login แล้วหรือยัง
// ถ้ายังไม่ได้ Login ให้กลับไปหน้า Login
// ----------------------------------------------------------
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// ----------------------------------------------------------
// ข้อมูลผู้ใช้ที่ Login อยู่
// ----------------------------------------------------------
$user = $_SESSION['user'];

// ----------------------------------------------------------
// นับจำนวนปัญหาแต่ละสถานะ
// ----------------------------------------------------------

// จำนวน "แจ้งเตือน"
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM repairs 
    WHERE status = 'แจ้งเตือน'
");
$stmt->execute();
$alertCount = $stmt->fetchColumn();

// จำนวน "กำลังแก้ไข"
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM repairs 
    WHERE status = 'กำลังแก้ไข'
");
$stmt->execute();
$repairingCount = $stmt->fetchColumn();

// จำนวน "แก้ไขเรียบร้อย"
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM repairs 
    WHERE status = 'แก้ไขเรียบร้อย'
");
$stmt->execute();
$completedCount = $stmt->fetchColumn();

// ----------------------------------------------------------
// ดึงรายการปัญหาเครื่องจักรล่าสุด
// เชื่อมข้อมูลจาก machines และ problems
// ----------------------------------------------------------
$stmt = $db->prepare("
    SELECT
        repairs.id,
        machines.machine_code,
        machines.machine_name,
        problems.problem_name,
        repairs.reporter,
        repairs.status,
        repairs.detail,
        repairs.started_at
    FROM repairs

    INNER JOIN machines
        ON repairs.machine_id = machines.id

    INNER JOIN problems
        ON repairs.problem_id = problems.id

    ORDER BY repairs.started_at DESC
");

$stmt->execute();

$repairs = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Dashboard - Machine Stop Monitor</title>

    <!-- เรียกใช้ CSS หลัก -->
    <link rel="stylesheet" href="css/style.css">

</head>

<body class="dashboard-page">

    <!-- =====================================================
         ส่วนหัวของระบบ
    ====================================================== -->
    <header class="dashboard-header">

        <div>

            <!-- โลโก้ กดแล้วกลับหน้า Dashboard -->
            <a
                href="dashboard.php"
                class="site-logo"
            >
                Machine Stop Monitor
            </a>

            <p>
                ระบบบันทึกและติดตามเครื่องจักรหยุดทำงาน
            </p>

        </div>

        <div class="user-area">

            <span>
                👤 <?php echo htmlspecialchars($user['full_name']); ?>
            </span>

            <a href="logout.php" class="logout-btn">
                ออกจากระบบ
            </a>

        </div>

    </header>


    <!-- =====================================================
         เนื้อหาหลัก
    ====================================================== -->
    <main class="dashboard-container">


        <!-- =================================================
             การ์ดสรุปสถานะ
        ================================================== -->
        <section class="status-cards">


            <!-- แจ้งเตือน -->
            <div class="status-card alert-card">

                <div class="status-icon">
                    🔴
                </div>

                <div>

                    <h3>แจ้งเตือน</h3>

                    <strong>
                        <?php echo $alertCount; ?>
                    </strong>

                    <p>
                        รายการ
                    </p>

                </div>

            </div>


            <!-- กำลังแก้ไข -->
            <div class="status-card repairing-card">

                <div class="status-icon">
                    🟡
                </div>

                <div>

                    <h3>กำลังแก้ไข</h3>

                    <strong>
                        <?php echo $repairingCount; ?>
                    </strong>

                    <p>
                        รายการ
                    </p>

                </div>

            </div>


            <!-- แก้ไขเรียบร้อย -->
            <div class="status-card completed-card">

                <div class="status-icon">
                    🟢
                </div>

                <div>

                    <h3>แก้ไขเรียบร้อย</h3>

                    <strong>
                        <?php echo $completedCount; ?>
                    </strong>

                    <p>
                        รายการ
                    </p>

                </div>

            </div>

        </section>


        <!-- =================================================
             เมนูระบบ
        ================================================== -->
        <section class="dashboard-menu">

            <!-- เพิ่มประเภทปัญหาใหม่ -->
            <a href="problem.php" class="menu-button">
                🛠️ บันทึกปัญหาเครื่องจักร
            </a>

            <!-- แจ้งว่าเครื่องจักรหยุด -->
            <a href="repair.php" class="menu-button">
                📷 แจ้งเครื่องจักรหยุด
            </a>

            <!-- ดูประวัติ -->
            <a href="history.php" class="menu-button">
                📋 ประวัติการซ่อม
            </a>

        </section>


        <!-- =================================================
             ตารางปัญหาเครื่องจักร
        ================================================== -->
        <section class="repair-list">

            <div class="section-title">

                <div>

                    <h2>
                        รายการเครื่องจักร
                    </h2>

                    <p>
                        รายการปัญหาล่าสุดในระบบ
                    </p>

                </div>

            </div>


            <?php if (count($repairs) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>เครื่องจักร</th>

                                <th>ปัญหา</th>

                                <th>ผู้แจ้ง</th>

                                <th>สถานะ</th>

                                <th>เวลาแจ้ง</th>

                                <th>จัดการ</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($repairs as $repair): ?>

                                <tr>

                                    <!-- เครื่องจักร -->
                                    <td>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $repair['machine_code']
                                            );
                                            ?>
                                        </strong>

                                        <br>

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

                                        <?php
                                        echo htmlspecialchars(
                                            $repair['problem_name']
                                        );
                                        ?>

                                        <?php if (!empty($repair['detail'])): ?>

                                            <br>

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

                                            $statusClass = 'status-alert';

                                        } elseif (
                                            $repair['status']
                                            === 'กำลังแก้ไข'
                                        ) {

                                            $statusClass = 'status-repairing';

                                        } elseif (
                                            $repair['status']
                                            === 'แก้ไขเรียบร้อย'
                                        ) {

                                            $statusClass = 'status-completed';

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

                                    <td>

                                        <a
                                            href="repair_detail.php?id=<?php
                                                echo $repair['id'];
                                            ?>"
                                            class="detail-btn"
                                        >
                                            ดูรายละเอียด
                                        </a>

                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-data">

                    ยังไม่มีข้อมูลเครื่องจักร

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>

</html>