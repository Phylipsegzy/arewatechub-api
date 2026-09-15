<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background-color: #f9f9f9;">
    <h2 style="color: #DF2328; text-align: center;">New Cohort Enrollment</h2>
    <ul>
        <li><strong>Student:</strong> {{ $enrollment->customer->firstname }} {{ $enrollment->customer->lastname }} ({{ $enrollment->customer->email }})</li>
        <li><strong>Batch:</strong> {{ $enrollment->intake->name }}</li>
        <li><strong>Track:</strong> {{ $enrollment->track_selected }}</li>
        <li><strong>Status:</strong> {{ $enrollment->status_type }}</li>
        <li><strong>Amount due:</strong> ₦{{ number_format($enrollment->amount_due, 2) }}</li>
    </ul>
</div>
