<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px; background-color: #f9f9f9;">
    <h2 style="color: #DF2328; text-align: center;">Booking Confirmed</h2>
    <p>Hi {{ $booking->customer->firstname }},</p>
    <p>Your workspace booking is confirmed:</p>
    <ul>
        <li><strong>Room:</strong> {{ $booking->room->name }}</li>
        <li><strong>Seat:</strong> {{ $booking->seat_number }}</li>
        <li><strong>Date:</strong> {{ $booking->start_date }}{{ $booking->start_date != $booking->end_date ? ' – ' . $booking->end_date : '' }}</li>
        <li><strong>Amount paid:</strong> ₦{{ number_format($booking->price, 2) }}</li>
    </ul>
    <p>See you at ArewaTecHub!</p>
</div>
