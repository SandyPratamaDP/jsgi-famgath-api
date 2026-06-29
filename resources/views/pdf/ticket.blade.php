<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Gathering Ticket</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; }
        .container { padding: 24px; border: 1px solid #ddd; border-radius: 12px; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 24px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .row label { font-weight: bold; width: 180px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Ancol Family Gathering Ticket</h1>
            <p>Ticket for internal family gathering / access control.</p>
        </div>

        <div class="row"><label>NIK:</label><span>{{ $employee->nik }}</span></div>
        <div class="row"><label>Name:</label><span>{{ $employee->name }}</span></div>
        <div class="row"><label>Department:</label><span>{{ $employee->department }}</span></div>
        <div class="row"><label>Type:</label><span>{{ ucfirst($employee->employee_type) }}</span></div>
        <div class="row"><label>Transport:</label><span>{{ str_replace('_', ' ', ucfirst($employee->transport_type)) }}</span></div>
        <div class="row"><label>Total Vehicles:</label><span>{{ $employee->total_vehicles }}</span></div>
        <div class="row"><label>Headcount:</label><span>{{ $employee->total_passengers }}</span></div>
        @if($employee->is_pic_bus)
            <div class="row"><label>Bus PIC:</label><span>Yes</span></div>
            <div class="row"><label>Manifest Size:</label><span>{{ $employee->total_bus_passengers ?? 'N/A' }}</span></div>
        @endif
        @if($employee->bus_number)
            <div class="row"><label>Bus Number:</label><span>{{ $employee->bus_number }}</span></div>
        @endif
        <div class="row"><label>Attendance:</label><span>{{ ucfirst($employee->attendance_status) }}</span></div>
    </div>
</body>
</html>
