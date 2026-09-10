<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
    <h2 style="color: #DF2328;">New Workspace Booking</h2>
    <ul>
        <li><strong>Customer:</strong> {{ $booking->customer->firstname }} {{ $booking->customer->lastname }} ({{ $booking->customer->email }})</li>
        <li><strong>Plan:</strong> {{ $booking->plan->name }}</li>
        <li><strong>Room:</strong> {{ $booking->room->name }}, Seat {{ $booking->seat_number }}</li>
        <li><strong>Date:</strong> {{ $booking->start_date }}{{ $booking->start_date != $booking->end_date ? ' – ' . $booking->end_date : '' }}</li>
        <li><strong>Amount:</strong> ₦{{ number_format($booking->price, 2) }}</li>
    </ul>
</div>
