<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background-color: #f9f9f9;">
    <h2 style="color: #DF2328; text-align: center;">New Future Builders Camp Registration</h2>
    <ul>
        <li><strong>Child:</strong> {{ $registration->child_firstname }} {{ $registration->child_lastname }} (age {{ $registration->child_age }})</li>
        <li><strong>Parent/Guardian:</strong> {{ $registration->parent_name }} ({{ $registration->relationship }})</li>
        <li><strong>Phone:</strong> {{ $registration->parent_phone }}</li>
        <li><strong>Address:</strong> {{ $registration->parent_address }}, near {{ $registration->nearest_landmark }}</li>
        <li><strong>School:</strong> {{ $registration->school ?? '—' }}</li>
        <li><strong>Registration fee status:</strong> {{ ucfirst($registration->registration_payment_status) }}</li>
    </ul>
</div>
