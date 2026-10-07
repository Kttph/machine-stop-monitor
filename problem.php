<?php

/*
|--------------------------------------------------------------------------
| problem.php
|--------------------------------------------------------------------------
| หน้าสำหรับจัดการประเภทปัญหาเครื่องจักร
|
| ความสามารถ:
| 1. เพิ่มปัญหาใหม่
| 2. แก้ไขปัญหา
| 3. ลบปัญหา
| 4. ป้องกันการเพิ่มชื่อปัญหาซ้ำ
|
| ตารางที่ใช้:
| problems
|--------------------------------------------------------------------------
*/

// เริ่มใช้งาน Session
session_start();

// เรียกไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db.php';


// ----------------------------------------------------------
// ตรวจสอบ Login
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
// ตัวแปรสำหรับข้อความ
// ----------------------------------------------------------

$error = '';
$success = '';


// ----------------------------------------------------------
// ตรวจสอบข้อมูลจาก POST
// ----------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    $problemId = $_POST['problem_id'] ?? '';

    $problemName = trim(
        $_POST['problem_name'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );


    // ======================================================
    // เพิ่มปัญหาใหม่
    // ======================================================

    if ($action === 'add') {

        // ตรวจสอบว่ากรอกชื่อปัญหาหรือไม่
        if ($problemName === '') {

            $error = 'กรุณากรอกชื่อปัญหา';

        } else {

            // --------------------------------------------------
            // ตรวจสอบชื่อปัญหาซ้ำ
            // --------------------------------------------------

            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM problems
                WHERE problem_name = ?
            ");

            $stmt->execute([
                $problemName
            ]);

            $duplicate = $stmt->fetchColumn();


            if ($duplicate > 0) {

                $error =
                    'ไม่สามารถเพิ่มได้ เพราะมีปัญหานี้อยู่ในระบบแล้ว';

            } else {

                try {

                    // เพิ่มข้อมูลปัญหา
                    $stmt = $db->prepare("
                        INSERT INTO problems (
                            problem_name,
                            description
                        )
                        VALUES (?, ?)
                    ");

                    $stmt->execute([
                        $problemName,
                        $description
                    ]);

                    $success =
                        'เพิ่มปัญหาใหม่เรียบร้อยแล้ว';

                } catch (PDOException $e) {

                    $error =
                        'เกิดข้อผิดพลาดในการบันทึกข้อมูล';

                }

            }

        }

    }


    // ======================================================
    // แก้ไขปัญหา
    // ======================================================

    elseif ($action === 'edit') {

        // ตรวจสอบข้อมูล
        if (
            $problemId === '' ||
            $problemName === ''
        ) {

            $error =
                'กรุณากรอกข้อมูลให้ครบ';

        } else {

            // --------------------------------------------------
            // ตรวจสอบว่าชื่อใหม่ซ้ำกับรายการอื่นหรือไม่
            // --------------------------------------------------

            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM problems
                WHERE problem_name = ?
                AND id != ?
            ");

            $stmt->execute([
                $problemName,
                $problemId
            ]);

            $duplicate = $stmt->fetchColumn();


            if ($duplicate > 0) {

                $error =
                    'ไม่สามารถแก้ไขได้ เพราะชื่อปัญหานี้มีอยู่แล้ว';

            } else {

                try {

                    // อัปเดตข้อมูล
                    $stmt = $db->prepare("
                        UPDATE problems
                        SET
                            problem_name = ?,
                            description = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $problemName,
                        $description,
                        $problemId
                    ]);

                    $success =
                        'แก้ไขข้อมูลปัญหาเรียบร้อยแล้ว';

                } catch (PDOException $e) {

                    $error =
                        'เกิดข้อผิดพลาดในการแก้ไขข้อมูล';

                }

            }

        }

    }


    // ======================================================
    // ลบปัญหา
    // ======================================================

    elseif ($action === 'delete') {

        if ($problemId === '') {

            $error =
                'ไม่พบข้อมูลปัญหาที่ต้องการลบ';

        } else {

            try {

                // ------------------------------------------------
                // ตรวจสอบว่าปัญหานี้ถูกใช้ในประวัติการซ่อมหรือไม่
                // ------------------------------------------------

                $stmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM repairs
                    WHERE problem_id = ?
                ");

                $stmt->execute([
                    $problemId
                ]);

                $used = $stmt->fetchColumn();


                if ($used > 0) {

                    $error =
                        'ไม่สามารถลบได้ เพราะปัญหานี้มีการใช้งานในประวัติการซ่อมแล้ว';

                } else {

                    // ลบข้อมูล
                    $stmt = $db->prepare("
                        DELETE FROM problems
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $problemId
                    ]);

                    $success =
                        'ลบปัญหาเรียบร้อยแล้ว';

                }

            } catch (PDOException $e) {

                $error =
                    'ไม่สามารถลบข้อมูลได้';

            }

        }

    }

}


// ----------------------------------------------------------
// ถ้ามีการกดแก้ไข
// ดึงข้อมูลปัญหามาแสดงในฟอร์ม
// ----------------------------------------------------------

$editProblem = null;

if (isset($_GET['edit'])) {

    $editId = $_GET['edit'];

    $stmt = $db->prepare("
        SELECT
            id,
            problem_name,
            description
        FROM problems
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $editId
    ]);

    $editProblem = $stmt->fetch();


    if (!$editProblem) {

        $error =
            'ไม่พบข้อมูลปัญหาที่ต้องการแก้ไข';

    }

}


// ----------------------------------------------------------
// ดึงรายการปัญหาทั้งหมด
// ----------------------------------------------------------

$stmt = $db->prepare("
    SELECT
        id,
        problem_name,
        description
    FROM problems
    ORDER BY id ASC
");

$stmt->execute();

$problems = $stmt->fetchAll();

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
        บันทึกปัญหาเครื่องจักร
    </title>

    <!-- เรียกใช้ CSS หลัก -->
    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <!-- =====================================================
         CSS เฉพาะหน้า Problem
    ====================================================== -->

    <style>

        /* --------------------------------------------------
           Container หลัก
        -------------------------------------------------- */

        .problem-container {

            max-width: 1100px;

            margin: 30px auto;

            padding: 0 20px;

        }


        /* --------------------------------------------------
           Grid แบ่งซ้าย / ขวา
        -------------------------------------------------- */

        .problem-grid {

            display: grid;

            grid-template-columns:
                minmax(300px, 1fr)
                minmax(400px, 1.5fr);

            gap: 25px;

        }


        /* --------------------------------------------------
           กล่องแต่ละส่วน
        -------------------------------------------------- */

        .problem-card {

            background: white;

            border-radius: 14px;

            padding: 25px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.07);

        }


        .problem-card h2 {

            margin: 0 0 8px;

            color: #172b4d;

        }


        .problem-card > p {

            color: #64748b;

            margin: 0 0 25px;

            font-size: 14px;

        }


        /* --------------------------------------------------
           ช่องกรอกข้อมูล
        -------------------------------------------------- */

        .problem-card .form-group {

            margin-bottom: 20px;

        }


        .problem-card .form-group label {

            display: block;

            margin-bottom: 8px;

            color: #334155;

            font-weight: bold;

        }


        .problem-card .form-group label span {

            color: #dc3545;

        }


        .problem-card .form-group input,
        .problem-card .form-group textarea {

            width: 100%;

            box-sizing: border-box;

            padding: 12px 14px;

            border: 1px solid #cbd5e1;

            border-radius: 8px;

            font-family: inherit;

            font-size: 15px;

            outline: none;

        }


        .problem-card .form-group input:focus,
        .problem-card .form-group textarea:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.12);

        }


        .problem-card .form-group textarea {

            min-height: 120px;

            resize: vertical;

        }


        /* --------------------------------------------------
           ปุ่มฟอร์ม
        -------------------------------------------------- */

        .problem-actions {

            display: flex;

            gap: 10px;

            margin-top: 20px;

        }


        .save-problem-btn {

            border: none;

            background: #2563eb;

            color: white;

            padding: 11px 18px;

            border-radius: 7px;

            cursor: pointer;

            font-family: inherit;

            font-size: 14px;

            font-weight: bold;

        }


        .save-problem-btn:hover {

            background: #1d4ed8;

        }


        .cancel-problem-btn {

            display: inline-block;

            background: #64748b;

            color: white;

            text-decoration: none;

            padding: 11px 18px;

            border-radius: 7px;

            font-size: 14px;

            font-weight: bold;

        }


        .cancel-problem-btn:hover {

            background: #475569;

        }


        /* --------------------------------------------------
           ตารางปัญหา
        -------------------------------------------------- */

        .problem-table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        .problem-table {

            width: 100%;

            min-width: 650px;

            border-collapse: collapse;

        }


        .problem-table th,
        .problem-table td {

            padding: 12px;

            border-bottom:
                1px solid #e2e8f0;

            text-align: left;

            vertical-align: top;

        }


        .problem-table th {

            background: #f8fafc;

            color: #475569;

            font-size: 13px;

        }


        .problem-table td {

            color: #475569;

            font-size: 14px;

        }


        .problem-name {

            font-weight: bold;

            color: #1e293b;

        }


        /* --------------------------------------------------
           ปุ่มแก้ไข
        -------------------------------------------------- */

        .problem-edit-btn {

            display: inline-block;

            padding: 7px 10px;

            background: #fef3c7;

            color: #92400e;

            text-decoration: none;

            border-radius: 6px;

            font-size: 12px;

            font-weight: bold;

            margin-right: 4px;

        }


        .problem-edit-btn:hover {

            background: #fde68a;

        }


        /* --------------------------------------------------
           ปุ่มลบ
        -------------------------------------------------- */

        .problem-delete-btn {

            border: none;

            padding: 7px 10px;

            background: #fee2e2;

            color: #b91c1c;

            border-radius: 6px;

            cursor: pointer;

            font-family: inherit;

            font-size: 12px;

            font-weight: bold;

        }


        .problem-delete-btn:hover {

            background: #fecaca;

        }


        /* --------------------------------------------------
           ข้อความสำเร็จ / Error
        -------------------------------------------------- */

        .problem-success {

            background: #dcfce7;

            color: #166534;

            border-left:
                4px solid #22c55e;

            padding: 13px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

        }


        .problem-error {

            background: #fee2e2;

            color: #b91c1c;

            border-left:
                4px solid #dc3545;

            padding: 13px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

        }


        /* --------------------------------------------------
           Responsive
        -------------------------------------------------- */

        @media (max-width: 800px) {

            .problem-container {

                padding: 0 12px;

                margin-top: 20px;

            }


            .problem-grid {

                grid-template-columns: 1fr;

            }


            .problem-card {

                padding: 20px;

            }


            .problem-actions {

                flex-direction: column;

            }


            .save-problem-btn,
            .cancel-problem-btn {

                width: 100%;

                text-align: center;

                box-sizing: border-box;

            }

        }

    </style>

</head>


<body class="dashboard-page">


    <!-- =====================================================
         Header
    ====================================================== -->

    <header class="dashboard-header">

        <div>

            <!-- กดชื่อระบบเพื่อกลับ Dashboard -->
            <a
                href="dashboard.php"
                class="site-logo"
            >
                Machine Stop Monitor
            </a>

            <p>
                บันทึกปัญหาเครื่องจักร
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


            <!-- กลับหน้า Dashboard -->

            <a
                href="dashboard.php"
                class="back-btn"
            >
                ← Dashboard
            </a>


            <!-- ออกจากระบบ -->

            <a
                href="logout.php"
                class="logout-btn"
            >
                ออกจากระบบ
            </a>

        </div>

    </header>


    <!-- =====================================================
         Main
    ====================================================== -->

    <main class="problem-container">


        <!-- =================================================
             ข้อความแจ้งเตือน
        ================================================== -->

        <?php if ($success !== ''): ?>

            <div class="problem-success">

                ✅

                <?php

                echo htmlspecialchars(
                    $success
                );

                ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="problem-error">

                ⚠️

                <?php

                echo htmlspecialchars(
                    $error
                );

                ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             Grid
        ================================================== -->

        <div class="problem-grid">


            <!-- =================================================
                 เพิ่ม / แก้ไขปัญหา
            ================================================== -->

            <section class="problem-card">

                <?php if ($editProblem): ?>

                    <h2>
                        ✏️ แก้ไขปัญหา
                    </h2>

                    <p>
                        แก้ไขข้อมูลประเภทปัญหา
                    </p>

                <?php else: ?>

                    <h2>
                        🛠️ เพิ่มปัญหาใหม่
                    </h2>

                    <p>
                        เพิ่มประเภทปัญหาเครื่องจักรเข้าสู่ระบบ
                    </p>

                <?php endif; ?>


                <form method="POST">


                    <!-- ------------------------------------------------
                         กำหนด Action
                    ------------------------------------------------- -->

                    <?php if ($editProblem): ?>

                        <input
                            type="hidden"
                            name="action"
                            value="edit"
                        >

                        <input
                            type="hidden"
                            name="problem_id"
                            value="<?php
                                echo htmlspecialchars(
                                    $editProblem['id']
                                );
                            ?>"
                        >

                    <?php else: ?>

                        <input
                            type="hidden"
                            name="action"
                            value="add"
                        >

                    <?php endif; ?>


                    <!-- ------------------------------------------------
                         ชื่อปัญหา
                    ------------------------------------------------- -->

                    <div class="form-group">

                        <label for="problem_name">

                            ชื่อปัญหา

                            <span>*</span>

                        </label>


                        <input
                            type="text"
                            name="problem_name"
                            id="problem_name"
                            placeholder="เช่น น้ำมันรั่ว"
                            value="<?php

                            if ($editProblem) {

                                echo htmlspecialchars(
                                    $editProblem['problem_name']
                                );

                            } else {

                                echo htmlspecialchars(
                                    $_POST['problem_name']
                                    ?? ''
                                );

                            }

                            ?>"
                            required
                        >

                    </div>


                    <!-- ------------------------------------------------
                         รายละเอียด
                    ------------------------------------------------- -->

                    <div class="form-group">

                        <label for="description">

                            รายละเอียดปัญหา

                        </label>


                        <textarea
                            name="description"
                            id="description"
                            placeholder="อธิบายรายละเอียดของปัญหา..."
                        ><?php

                        if ($editProblem) {

                            echo htmlspecialchars(
                                $editProblem['description']
                            );

                        } else {

                            echo htmlspecialchars(
                                $_POST['description']
                                ?? ''
                            );

                        }

                        ?></textarea>

                    </div>


                    <!-- ------------------------------------------------
                         ปุ่ม
                    ------------------------------------------------- -->

                    <div class="problem-actions">

                        <button
                            type="submit"
                            class="save-problem-btn"
                        >

                            💾

                            <?php

                            echo $editProblem
                                ? 'บันทึกการแก้ไข'
                                : 'เพิ่มปัญหา';

                            ?>

                        </button>


                        <?php if ($editProblem): ?>

                            <a
                                href="problem.php"
                                class="cancel-problem-btn"
                            >
                                ยกเลิก
                            </a>

                        <?php endif; ?>

                    </div>


                </form>

            </section>


            <!-- =================================================
                 รายการปัญหาที่มีอยู่
            ================================================== -->

            <section class="problem-card">

                <h2>
                    📋 ปัญหาที่มีอยู่
                </h2>

                <p>
                    รายการประเภทปัญหาที่ใช้ในระบบ
                </p>


                <?php if (count($problems) > 0): ?>

                    <div class="problem-table-wrapper">

                        <table class="problem-table">

                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        ปัญหา
                                    </th>

                                    <th>
                                        รายละเอียด
                                    </th>

                                    <th>
                                        จัดการ
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach (
                                    $problems as $problem
                                ): ?>

                                    <tr>

                                        <!-- ลำดับ -->

                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $problem['id']
                                            );
                                            ?>

                                        </td>


                                        <!-- ชื่อปัญหา -->

                                        <td>

                                            <div class="problem-name">

                                                <?php

                                                echo htmlspecialchars(
                                                    $problem['problem_name']
                                                );

                                                ?>

                                            </div>

                                        </td>


                                        <!-- รายละเอียด -->

                                        <td>

                                            <?php

                                            if (
                                                !empty(
                                                    $problem['description']
                                                )
                                            ) {

                                                echo htmlspecialchars(
                                                    $problem['description']
                                                );

                                            } else {

                                                echo '-';

                                            }

                                            ?>

                                        </td>


                                        <!-- จัดการ -->

                                        <td>

                                            <!-- ปุ่มแก้ไข -->

                                            <a
                                                href="problem.php?edit=<?php
                                                    echo $problem['id'];
                                                ?>"
                                                class="problem-edit-btn"
                                            >
                                                แก้ไข
                                            </a>


                                            <!-- ปุ่มลบ -->

                                            <form
                                                method="POST"
                                                style="display:inline;"
                                                onsubmit="
                                                    return confirm(
                                                        'ต้องการลบปัญหานี้หรือไม่?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="problem_id"
                                                    value="<?php
                                                        echo $problem['id'];
                                                    ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    class="problem-delete-btn"
                                                >
                                                    ลบ
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty-data">

                        ยังไม่มีข้อมูลปัญหา

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>


</body>

</html>