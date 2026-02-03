<?php
require_once '../config/database.php';
include '../includes/header.php';

// Fetch Machines for dropdown
$machines = $pdo->query("SELECT * FROM machines WHERE status = 'Active'")->fetchAll();
$tools = $pdo->query("SELECT * FROM tooling WHERE status = 'Active'")->fetchAll();

// Check items (Mocked for now)
$checkItems = [
    ['id' => 1, 'name' => 'Machine cleanliness / ความสะอาดเครื่องจักร', 'desc' => 'Check for dust, oil leaks'],
    ['id' => 2, 'name' => 'Safety guard / ฝาครอบนิรภัย', 'desc' => 'Properly installed and closed'],
    ['id' => 3, 'name' => 'Emergency switch / สวิตช์ฉุกเฉิน', 'desc' => 'Functional test'],
    ['id' => 4, 'name' => 'Electrical system / ระบบไฟฟ้า', 'desc' => 'Check cables and connectors'],
];
?>

<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card card-premium">
            <div class="card-body p-5">
                <form action="save_check" method="POST" id="checkSheetForm">
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Select Target / เลือกรายการ</label>
                            <select class="form-select form-select-lg rounded-3" name="target_id" required>
                                <option value="">-- Choose Machine or Tool --</option>
                                <optgroup label="Machines">
                                    <?php foreach ($machines as $m): ?>
                                        <option value="m_<?php echo $m['id']; ?>">
                                            <?php echo $m['machine_code'] . ' - ' . $m['machine_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="m_1">MCH-001 (Demo Machine)</option>
                                </optgroup>
                                <optgroup label="Tooling">
                                    <?php foreach ($tools as $t): ?>
                                        <option value="t_<?php echo $t['id']; ?>">
                                            <?php echo $t['tool_code'] . ' - ' . $t['tool_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="t_1">TOOL-A5 (Demo Tool)</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Inspector Name / ชื่อผู้ตรวจสอบ</label>
                            <input type="text" class="form-control form-control-lg rounded-3" name="inspector_name"
                                placeholder="Enter your name" required>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-custom align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 40%;">Check Item / รายการที่ต้องตรวจสอบ</th>
                                    <th class="text-center">Status / สถานะ</th>
                                    <th>Remarks / หมายเหตุ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checkItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold">
                                                <?php echo $item['name']; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $item['desc']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio"
                                                        name="result[<?php echo $item['id']; ?>]"
                                                        id="ok_<?php echo $item['id']; ?>" value="OK" required>
                                                    <label class="form-check-label text-success fw-bold"
                                                        for="ok_<?php echo $item['id']; ?>">OK</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio"
                                                        name="result[<?php echo $item['id']; ?>]"
                                                        id="ng_<?php echo $item['id']; ?>" value="NG" required>
                                                    <label class="form-check-label text-danger fw-bold"
                                                        for="ng_<?php echo $item['id']; ?>">NG</label>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm rounded-2"
                                                name="comment[<?php echo $item['id']; ?>]" placeholder="Add details if NG">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Overall Notes / บันทึกเพิ่มเติม</label>
                        <textarea class="form-control rounded-3" name="overall_remarks" rows="3"
                            placeholder="Identify issues found..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-3">
                        <button type="reset" class="btn btn-light px-4 py-2 rounded-pill">Cancel</button>
                        <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill fw-bold shadow">
                            <i class="fas fa-save me-2"></i> Submit Inspection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>