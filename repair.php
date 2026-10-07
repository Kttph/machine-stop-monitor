<?php

/*
|--------------------------------------------------------------------------
| repair.php
|--------------------------------------------------------------------------
| หน้าสำหรับแจ้งเครื่องจักรหยุด
|
| การทำงาน:
| 1. เลือกเครื่องจักร
| 2. เลือกปัญหา
| 3. กรอกผู้แจ้ง
| 4. กรอกรายละเอียดอาการ
| 5. แนบรูปภาพ / ใช้กล้องมือถือ
| 6. บันทึกข้อมูลลง SQLite
| 7. บันทึกเสร็จแล้วไปหน้ารายละเอียดงานซ่อมทันที
|
| สถานะเริ่มต้น:
| "แจ้งเตือน"
|--------------------------------------------------------------------------
*/

session_start();

require_once 'db.php';


// ----------------------------------------------------------
// ตรวจสอบ Login
// ----------------------------------------------------------

if (!isset($_SESSION['user'])) {

    header('Location: index.php');
    exit;

}

$user = $_SESSION['user'];


// ----------------------------------------------------------
// ตัวแปรสำหรับแสดงข้อความ
// ----------------------------------------------------------

$error = '';


// ----------------------------------------------------------
// ดึงรายชื่อเครื่องจักร
// ----------------------------------------------------------

$stmt = $db->prepare("
    SELECT
        id,
        machine_code,
        machine_name
    FROM machines
    ORDER BY machine_code ASC
");

$stmt->execute();

$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ----------------------------------------------------------
// ดึงรายการปัญหา
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

$problems = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ----------------------------------------------------------
// เมื่อกดปุ่มบันทึก
// ----------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $machineId = $_POST['machine_id'] ?? '';

    $problemId = $_POST['problem_id'] ?? '';

    $reporter = trim(
        $_POST['reporter'] ?? ''
    );

    $detail = trim(
        $_POST['detail'] ?? ''
    );


    // ------------------------------------------------------
    // ตรวจสอบข้อมูลที่จำเป็น
    // ------------------------------------------------------

    if (
        $machineId === '' ||
        $problemId === '' ||
        $reporter === ''
    ) {

        $error =
            'กรุณากรอกข้อมูลที่จำเป็นให้ครบ';

    } else {


        // --------------------------------------------------
        // ตรวจสอบว่าเครื่อง + ปัญหา
        // มีรายการที่ยังไม่แก้เสร็จอยู่หรือไม่
        // --------------------------------------------------

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM repairs
            WHERE machine_id = ?
            AND problem_id = ?
            AND status != 'แก้ไขเรียบร้อย'
        ");

        $stmt->execute([
            $machineId,
            $problemId
        ]);

        $duplicate = $stmt->fetchColumn();


        if ($duplicate > 0) {

            $error =
                'ไม่สามารถบันทึกได้ เพราะเครื่องจักรนี้มีปัญหานี้อยู่ในระบบแล้ว';

        } else {


            // ------------------------------------------------
            // จัดการรูปภาพ
            // ------------------------------------------------

            $imagePath = null;


            if (
                isset($_FILES['image']) &&
                $_FILES['image']['error'] === UPLOAD_ERR_OK
            ) {

                $uploadDir =
                    __DIR__ . '/uploads/';


                // สร้างโฟลเดอร์ uploads
                // หากยังไม่มี
                if (!is_dir($uploadDir)) {

                    mkdir(
                        $uploadDir,
                        0777,
                        true
                    );

                }


                $tmpName =
                    $_FILES['image']['tmp_name'];

                $originalName =
                    $_FILES['image']['name'];

                $fileSize =
                    $_FILES['image']['size'];


                // ------------------------------------------------
                // จำกัดขนาดไฟล์ 5 MB
                // ------------------------------------------------

                if (
                    $fileSize >
                    5 * 1024 * 1024
                ) {

                    $error =
                        'รูปภาพมีขนาดใหญ่เกินไป (สูงสุด 5 MB)';

                } else {


                    // ------------------------------------------------
                    // ตรวจสอบประเภทไฟล์
                    // ------------------------------------------------

                    $allowedTypes = [
                        'image/jpeg',
                        'image/png',
                        'image/webp'
                    ];


                    $fileType =
                        mime_content_type($tmpName);


                    if (
                        !in_array(
                            $fileType,
                            $allowedTypes
                        )
                    ) {

                        $error =
                            'อนุญาตเฉพาะไฟล์ JPG, PNG หรือ WEBP เท่านั้น';

                    } else {


                        // ------------------------------------------------
                        // สร้างชื่อไฟล์ใหม่
                        // ------------------------------------------------

                        $extension =
                            strtolower(
                                pathinfo(
                                    $originalName,
                                    PATHINFO_EXTENSION
                                )
                            );


                        $newFileName =
                            'repair_' .
                            date('Ymd_His') .
                            '_' .
                            uniqid() .
                            '.' .
                            $extension;


                        $targetFile =
                            $uploadDir .
                            $newFileName;


                        if (
                            move_uploaded_file(
                                $tmpName,
                                $targetFile
                            )
                        ) {

                            $imagePath =
                                'uploads/' .
                                $newFileName;

                        } else {

                            $error =
                                'ไม่สามารถบันทึกรูปภาพได้';

                        }

                    }

                }

            }


            // ------------------------------------------------------
            // บันทึกลงฐานข้อมูล
            // ------------------------------------------------------

            if ($error === '') {

                try {

                    $startedAt =
                        date('Y-m-d H:i:s');


                    $stmt = $db->prepare("
                        INSERT INTO repairs (
                            machine_id,
                            problem_id,
                            reporter,
                            status,
                            detail,
                            image,
                            started_at,
                            created_by
                        )
                        VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?
                        )
                    ");


                    $stmt->execute([
                        $machineId,
                        $problemId,
                        $reporter,
                        'แจ้งเตือน',
                        $detail,
                        $imagePath,
                        $startedAt,
                        $user['id']
                    ]);


                    // ==========================================================
                    // ดึง ID ของรายการที่เพิ่งบันทึก
                    // ==========================================================
                    //
                    // ต้องใช้ ID นี้เพื่อให้หน้า
                    // repair_detail.php รู้ว่าต้องแสดงงานซ่อมรายการไหน
                    //

                    $repairId =
                        $db->lastInsertId();


                    // ==========================================================
                    // ไปหน้ารายละเอียดงานซ่อมทันที
                    // ==========================================================
                    //
                    // ตัวอย่าง:
                    // repair_detail.php?id=6
                    //

                    header(
                        'Location: repair_detail.php?id=' .
                        $repairId
                    );

                    exit;


                } catch (PDOException $e) {

                    // ------------------------------------------------------
                    // ถ้าบันทึกฐานข้อมูลไม่ได้
                    // และมีรูปถูกอัปโหลดไปแล้ว
                    // ให้ลบรูปนั้นออก
                    // ------------------------------------------------------

                    if (
                        $imagePath !== null &&
                        file_exists(
                            __DIR__ . '/' . $imagePath
                        )
                    ) {

                        unlink(
                            __DIR__ . '/' . $imagePath
                        );

                    }


                    $error =
                        'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' .
                        $e->getMessage();

                }

            }

        }

    }

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
        แจ้งเครื่องจักรหยุด
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <style>

        /*
        |--------------------------------------------------------------------------
        | CSS สำหรับหน้านี้
        |--------------------------------------------------------------------------
        */

        .problem-description {

            margin-top: 8px;

            padding: 10px;

            background: #f8fafc;

            border-radius: 8px;

            color: #64748b;

            font-size: 14px;

        }

    </style>

</head>


<body class="dashboard-page">


    <!-- =====================================================
         Header
    ====================================================== -->

    <header class="dashboard-header">

        <div>
            <a href="dashboard.php" class="site-logo">
                Machine Stop Monitor
            </a>

            <p>
                แจ้งเครื่องจักรหยุด
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


    <!-- =====================================================
         Main
    ====================================================== -->

    <main class="form-container">


        <section class="form-card">


            <div class="form-title">

                <h2>
                    📷 แจ้งเครื่องจักรหยุด
                </h2>

                <p>
                    กรุณากรอกข้อมูลเครื่องจักรที่หยุดทำงาน
                </p>

            </div>


            <!-- =================================================
                 แสดง Error
            ================================================== -->

            <?php if ($error !== ''): ?>

                <div class="form-error">

                    ⚠️

                    <?php

                    echo htmlspecialchars(
                        $error
                    );

                    ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 Form
            ================================================== -->

            <form
                method="POST"
                enctype="multipart/form-data"
            >


                <!-- =================================================
                     เครื่องจักร
                ================================================== -->

                <div class="form-group">

                    <label for="machine_id">

                        เครื่องจักร

                        <span>*</span>

                    </label>


                    <select
                        name="machine_id"
                        id="machine_id"
                        required
                    >

                        <option value="">

                            -- เลือกเครื่องจักร --

                        </option>


                        <?php foreach (
                            $machines
                            as $machine
                        ): ?>

                            <option
                                value="<?php
                                    echo htmlspecialchars(
                                        $machine['id']
                                    );
                                ?>"
                                <?php

                                if (
                                    (
                                        $_POST['machine_id']
                                        ?? ''
                                    )
                                    ==
                                    $machine['id']
                                ) {

                                    echo 'selected';

                                }

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


                <!-- =================================================
                     ปัญหา
                ================================================== -->

                <div class="form-group">

                    <label for="problem_id">

                        ปัญหา

                        <span>*</span>

                    </label>


                    <select
                        name="problem_id"
                        id="problem_id"
                        required
                    >

                        <option value="">

                            -- เลือกปัญหา --

                        </option>


                        <?php foreach (
                            $problems
                            as $problem
                        ): ?>

                            <option
                                value="<?php
                                    echo htmlspecialchars(
                                        $problem['id']
                                    );
                                ?>"
                                data-description="<?php
                                    echo htmlspecialchars(
                                        $problem['description']
                                        ?? ''
                                    );
                                ?>"
                                <?php

                                if (
                                    (
                                        $_POST['problem_id']
                                        ?? ''
                                    )
                                    ==
                                    $problem['id']
                                ) {

                                    echo 'selected';

                                }

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


                    <!-- แสดงรายละเอียดของประเภทปัญหา -->

                    <div
                        id="problemDescription"
                        class="problem-description"
                        style="display:none;"
                    ></div>

                </div>


                <!-- =================================================
                     ผู้แจ้ง
                ================================================== -->

                <div class="form-group">

                    <label for="reporter">

                        ผู้แจ้ง / แผนก

                        <span>*</span>

                    </label>


                    <input
                        type="text"
                        name="reporter"
                        id="reporter"
                        placeholder="เช่น ฝ่ายผลิต"
                        value="<?php

                        echo htmlspecialchars(
                            $_POST['reporter']
                            ?? ''
                        );

                        ?>"
                        required
                    >

                </div>


                <!-- =================================================
                     รายละเอียดอาการ
                ================================================== -->

                <div class="form-group">

                    <label for="detail">

                        รายละเอียดอาการ

                    </label>


                    <textarea
                        name="detail"
                        id="detail"
                        rows="5"
                        placeholder="อธิบายอาการของเครื่องจักร เช่น เครื่องเปิดไม่ติด มีเสียงผิดปกติ..."
                    ><?php

                    echo htmlspecialchars(
                        $_POST['detail']
                        ?? ''
                    );

                    ?></textarea>

                </div>


                <!-- =================================================
                     รูปภาพ
                ================================================== -->

                <div class="form-group">

                    <label for="image">

                        📷 รูปเครื่องจักร

                    </label>


                    <input
                        type="file"
                        name="image"
                        id="image"
                        accept="image/jpeg,image/png,image/webp"
                        capture="environment"
                    >


                    <small class="form-help">

                        สามารถถ่ายรูปจากกล้องมือถือได้
                        หรือเลือกไฟล์รูปจากเครื่อง
                        (สูงสุด 5 MB)

                    </small>

                </div>


                <!-- =================================================
                     ปุ่ม
                ================================================== -->

                <div class="form-actions">

                    <a
                        href="dashboard.php"
                        class="cancel-btn"
                    >
                        ยกเลิก
                    </a>


                    <button
                        type="submit"
                        class="save-btn"
                    >

                        💾 บันทึกแจ้งปัญหา

                    </button>

                </div>


            </form>

        </section>

    </main>


    <!-- =====================================================
         JavaScript
    ====================================================== -->

    <script>

        /*
        |--------------------------------------------------------------------------
        | แสดงรายละเอียดของปัญหาที่เลือก
        |--------------------------------------------------------------------------
        */

        const problemSelect =
            document.getElementById(
                'problem_id'
            );


        const problemDescription =
            document.getElementById(
                'problemDescription'
            );


        function checkProblem() {

            const selectedOption =
                problemSelect.options[
                    problemSelect.selectedIndex
                ];


            // ถ้ายังไม่ได้เลือกปัญหา
            if (
                !selectedOption ||
                selectedOption.value === ''
            ) {

                problemDescription.style.display =
                    'none';

                problemDescription.innerHTML =
                    '';

                return;

            }


            // --------------------------------------------------
            // แสดงรายละเอียดของปัญหา
            // --------------------------------------------------

            const description =
                selectedOption.dataset.description
                || '';


            if (description !== '') {

                problemDescription.innerHTML =
                    'รายละเอียด: ' +
                    description;

                problemDescription.style.display =
                    'block';

            } else {

                problemDescription.style.display =
                    'none';

                problemDescription.innerHTML =
                    '';

            }

        }


        // ทำงานเมื่อเปลี่ยนปัญหา

        problemSelect.addEventListener(
            'change',
            checkProblem
        );


        // ตรวจสอบตอนเปิดหน้า
        // กรณีมีข้อมูล POST เดิม

        checkProblem();

    </script>


</body>

</html>