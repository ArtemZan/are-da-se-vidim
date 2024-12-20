<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'google_config.php';
require_once 'calculate_perfect_time.php';

$shareId = $_GET['id'] ?? '';
$event = null;
$isLoggedIn = isset($_SESSION['user']);
$preferences = [];

if ($shareId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE share_link = ?");
        $stmt->execute([$shareId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($event) {
            // Get all preferences with user information
            $stmt = $pdo->prepare("
                SELECT dp.*, u.name, u.email, u.picture_url 
                FROM date_preferences dp 
                JOIN users u ON dp.user_id = u.id 
                WHERE dp.event_id = ? 
                ORDER BY dp.created_at DESC
            ");
            $stmt->execute([$event['id']]);
            $preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = 'Database error';
    }
}

// Initialize Google Client for login button
$client = new Google_Client();
$client->setClientId($google_config['client_id']);
$client->setClientSecret($google_config['client_secret']);
$client->setRedirectUri($google_config['redirect_uri']);
$client->addScope($google_config['scopes']);

// Store current URL for redirect after login
$_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Event</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <div class="container">
        <?php if ($event): ?>
            <h2>Event Details</h2>
            <div class="event-details">
                <p><strong>Event Name:</strong> <?php echo htmlspecialchars($event['event_name']); ?></p>
                <p><strong>Time Required:</strong> <?php echo htmlspecialchars($event['time_required']); ?></p>
                <p><strong>Created:</strong> <?php echo date('F j, Y, g:i a', strtotime($event['created_at'])); ?></p>
            </div>

            <?php if ($isLoggedIn): ?>
            <div class="user-info">
                <img src="<?php echo htmlspecialchars($_SESSION['user']['picture']); ?>" 
                     alt="Profile picture" 
                     class="profile-picture">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>

            <div class="preference-section">
                <h3>Add Your Availability</h3>
                <form id="preferenceForm">
                    <input type="hidden" id="eventId" value="<?php echo $event['id']; ?>">
                    <input type="hidden" id="userId" value="<?php echo $_SESSION['user']['id']; ?>">
                    
                    <div class="form-group datetime-group">
                        <div class="datetime-input">
                            <label for="startDateTime">From</label>
                            <input type="text" id="startDateTime" placeholder="Select start time" required>
                        </div>
                        <div class="arrow">→</div>
                        <div class="datetime-input">
                            <label for="endDateTime">To</label>
                            <input type="text" id="endDateTime" placeholder="Select end time" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="preferenceScore">Preference (1-10)</label>
                        <input type="range" id="preferenceScore" min="1" max="10" value="5" 
                               oninput="updatePreferenceColor(this.value)">
                        <span id="preferenceValue">5</span>
                    </div>

                    <button type="submit">Add Preference</button>
                </form>
            </div>
            <?php else: ?>
            <div class="login-section">
                <p>Please sign in with Google to add your availability preferences:</p>
                <a href="<?php echo $client->createAuthUrl(); ?>" class="google-login-btn">
                    Sign in with Google
                </a>
            </div>
            <?php endif; ?>

            <div class="existing-preferences">
                <h3>All Preferences</h3>
                <div id="preferencesContainer">
                    <?php foreach ($preferences as $pref): ?>
                        <div class="preference-item" 
                             style="background-color: <?php echo getPreferenceColor($pref['preference_score']); ?>">
                            <div class="preference-user">
                                <img src="<?php echo htmlspecialchars($pref['picture_url']); ?>" 
                                     alt="User picture" 
                                     class="user-picture">
                                <span class="user-name"><?php echo htmlspecialchars($pref['name']); ?></span>
                            </div>
                            <div class="preference-details">
                                <div class="dates">
                                    <?php echo date('M j, Y', strtotime($pref['start_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($pref['end_date'])); ?>
                                </div>
                                <div class="times">
                                    <?php echo date('H:i', strtotime($pref['start_date'])); ?> - 
                                    <?php echo date('H:i', strtotime($pref['end_date'])); ?>
                                </div>
                            </div>
                            <div class="preference-right">
                                <span class="score">
                                    Preference: <?php echo $pref['preference_score']; ?>/10
                                </span>
                                <?php if ($isLoggedIn && $_SESSION['user']['id'] == $pref['user_id']): ?>
                                <button type="button" 
                                        class="delete-btn"
                                        onclick="deletePreference(<?php echo $pref['id']; ?>)">
                                    ×
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php
            // Move perfect time calculation here
            $perfectPeriods = findPerfectTime($event['time_required'], $preferences);
            if (!empty($perfectPeriods)): ?>
                <div class="perfect-time-section">
                    <h3>Perfect Time Suggestions</h3>
                    <div class="perfect-time-list">
                        <?php foreach (array_slice($perfectPeriods, 0, 3) as $index => $period): ?>
                            <div class="perfect-time-item">
                                <div class="rank"><?php echo $index + 1; ?></div>
                                <div class="time-details">
                                    <div class="dates">
                                        <?php echo formatPeriodDateTime($period['start']); ?> - 
                                        <?php echo formatPeriodDateTime($period['end']); ?>
                                    </div>
                                    <div class="score">
                                        Total Score: <?php echo $period['score']; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <style>
                .user-info {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 20px;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 4px;
                }
                .profile-picture {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                }
                .user-picture {
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                }
                .logout-btn {
                    margin-left: auto;
                    padding: 5px 10px;
                    background: #dc3545;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    font-size: 14px;
                }
                .google-login-btn {
                    display: inline-block;
                    padding: 10px 20px;
                    background: #4285f4;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    margin: 20px 0;
                }
                .login-section {
                    text-align: center;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    margin: 20px 0;
                }
                .preference-user {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin-bottom: 8px;
                }
                .preference-item {
                    padding: 10px;
                    margin: 5px 0;
                    border-radius: 4px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .preference-details {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }
                .times {
                    font-size: 0.9em;
                    opacity: 0.8;
                }
                .preference-right {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .delete-btn {
                    background: rgba(0, 0, 0, 0.1);
                    border: none;
                    color: #333;
                    font-size: 20px;
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0;
                    line-height: 1;
                }
                .delete-btn:hover {
                    background: rgba(0, 0, 0, 0.2);
                }
                #preferenceScore {
                    width: 100%;
                }
                .preference-section {
                    margin-top: 2rem;
                    padding-top: 2rem;
                    border-top: 1px solid #ddd;
                }
                .flatpickr-day.has-preference {
                    background-color: var(--preference-color);
                    border-color: transparent;
                    color: white;
                }
                .flatpickr-day.has-preference:hover {
                    background-color: var(--preference-color);
                    opacity: 0.8;
                }
                .flatpickr-calendar.hasTime .flatpickr-time {
                    border-top: 1px solid #e6e6e6;
                }
                .datetime-group {
                    display: flex;
                    align-items: flex-end;
                    gap: 15px;
                }
                .datetime-input {
                    flex: 1;
                }
                .datetime-input input {
                    box-sizing: border-box;
                    width: 100%;
                }
                .arrow {
                    font-size: 24px;
                    margin-bottom: 10px;
                    color: #666;
                }
                .picker-title {
                    text-align: center;
                    padding: 10px;
                    font-weight: bold;
                    background: #f0f0f0;
                    border-bottom: 1px solid #ddd;
                }
                .done-button {
                    width: 100%;
                    padding: 8px;
                    background: #007bff;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    margin-top: 10px;
                }
                .done-button:hover {
                    background: #0056b3;
                }
                .flatpickr-calendar {
                    padding-bottom: 10px;
                }
                .perfect-time-section {
                    margin-top: 40px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border-top: 2px solid #e9ecef;
                }

                .perfect-time-section h3 {
                    margin-top: 0;
                    color: #2c3e50;
                    text-align: center;
                    margin-bottom: 20px;
                }

                .perfect-time-list {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .perfect-time-item {
                    display: flex;
                    align-items: center;
                    padding: 10px;
                    background: white;
                    border-radius: 6px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }

                .perfect-time-item .rank {
                    width: 30px;
                    height: 30px;
                    background: #007bff;
                    color: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    margin-right: 15px;
                }

                .perfect-time-item .time-details {
                    flex-grow: 1;
                }

                .perfect-time-item .dates {
                    font-weight: bold;
                    color: #2c3e50;
                }

                .perfect-time-item .score {
                    font-size: 0.9em;
                    color: #666;
                    margin-top: 4px;
                }
            </style>

            <script>
                // Store existing preferences
                const existingPreferences = <?php echo json_encode($preferences); ?>;
                
                async function deletePreference(preferenceId) {
                    if (!confirm('Are you sure you want to delete this preference?')) {
                        return;
                    }

                    try {
                        const response = await fetch('delete_preference.php', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                preferenceId: preferenceId
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error deleting preference: ' + (data.error || 'Unknown error'));
                        }
                    } catch (error) {
                        alert('Error deleting preference. Please try again.');
                        console.error('Error:', error);
                    }
                }

                function getPreferenceColor(score) {
                    // Convert score 1-10 to hue value (orange=30 to green=120)
                    const hue = 30 + (score - 1) * 10;
                    return `hsl(${hue}, 70%, 70%)`;
                }

                function updatePreferenceColor(value) {
                    document.getElementById('preferenceValue').textContent = value;
                }

                function isDateTimeInRange(date, startDate, endDate) {
                    const checkDate = new Date(date);
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    
                    // Set hours to 0 to compare only dates when checking if date should be disabled
                    const checkDateOnly = new Date(checkDate.getFullYear(), checkDate.getMonth(), checkDate.getDate());
                    const startDateOnly = new Date(start.getFullYear(), start.getMonth(), start.getDate());
                    const endDateOnly = new Date(end.getFullYear(), end.getMonth(), end.getDate());
                    
                    return checkDateOnly >= startDateOnly && checkDateOnly <= endDateOnly;
                }

                // Helper function to format preferences for display
                function formatPreferences() {
                    return existingPreferences.map(pref => ({
                        ...pref,
                        formattedDate: `${pref.start_date.split(' ')[0]}: ${
                            new Date(pref.start_date).toLocaleTimeString('en-US', { 
                                hour: '2-digit', 
                                minute: '2-digit',
                                hour12: false 
                            })} - ${
                            new Date(pref.end_date).toLocaleTimeString('en-US', { 
                                hour: '2-digit', 
                                minute: '2-digit',
                                hour12: false 
                            })}`
                    }));
                }

                // Common configuration for both pickers
                const pickerConfig = {
                    enableTime: true,
                    dateFormat: "Y-m-d H:i",
                    time_24hr: true,
                    minDate: "today",
                    minuteIncrement: 30,
                    onDayCreate: function(dObj, dStr, fp, dayElem) {
                        const currentDate = dayElem.dateObj;
                        const formattedPrefs = formatPreferences();
                        
                        // Check if this date has any preferences
                        const datePrefs = formattedPrefs.filter(pref => 
                            isDateTimeInRange(currentDate, pref.start_date, pref.end_date)
                        );

                        if (datePrefs.length > 0) {
                            dayElem.classList.add('has-preference');
                            // Set custom property for color based on highest preference
                            const highestPref = Math.max(...datePrefs.map(p => p.preference_score));
                            dayElem.style.setProperty(
                                '--preference-color', 
                                getPreferenceColor(highestPref)
                            );

                            // Add title with all preferences for this date
                            dayElem.title = datePrefs
                                .map(p => p.formattedDate + ` (Preference: ${p.preference_score}/10)`)
                                .join('\n');
                        }
                    }
                };

                // Add styles for the picker titles
                const titleStyle = document.createElement('style');
                titleStyle.textContent = `
                    .picker-title {
                        text-align: center;
                        padding: 10px;
                        font-weight: bold;
                        background: #f0f0f0;
                        border-bottom: 1px solid #ddd;
                    }
                `;
                document.head.appendChild(titleStyle);

                // Initialize Flatpickr for start date-time
                const startPicker = flatpickr("#startDateTime", {
                    ...pickerConfig,
                    onChange: function(selectedDates) {
                        if (selectedDates[0]) {
                            endPicker.set('minDate', selectedDates[0]);
                        }
                    },
                    onReady: function(selectedDates, dateStr, instance) {
                        // Add title to the calendar
                        const title = document.createElement('div');
                        title.className = 'picker-title';
                        title.textContent = 'Start date';
                        instance.calendarContainer.insertBefore(title, instance.calendarContainer.firstChild);

                        // Add Next button
                        const nextButton = document.createElement('button');
                        nextButton.className = 'done-button';
                        nextButton.innerHTML = 'Next →';
                        nextButton.addEventListener('click', (e) => {
                            e.preventDefault();
                            instance.close();
                            // Open the end date picker after a short delay
                            setTimeout(() => endPicker.open(), 100);
                        });
                        instance.calendarContainer.appendChild(nextButton);
                    }
                });

                // Initialize Flatpickr for end date-time
                const endPicker = flatpickr("#endDateTime", {
                    ...pickerConfig,
                    onReady: function(selectedDates, dateStr, instance) {
                        // Add title to the calendar
                        const title = document.createElement('div');
                        title.className = 'picker-title';
                        title.textContent = 'End date';
                        instance.calendarContainer.insertBefore(title, instance.calendarContainer.firstChild);

                        // Add Done button
                        const doneButton = document.createElement('button');
                        doneButton.className = 'done-button';
                        doneButton.textContent = 'Done';
                        doneButton.addEventListener('click', (e) => {
                            e.preventDefault();
                            instance.close();
                        });
                        instance.calendarContainer.appendChild(doneButton);
                    }
                });

                // Add hover styles for the tooltips
                const style = document.createElement('style');
                style.textContent = `
                    .flatpickr-day[title] {
                        position: relative;
                    }
                    .flatpickr-day[title]:hover::after {
                        content: attr(title);
                        position: absolute;
                        bottom: 100%;
                        left: 50%;
                        transform: translateX(-50%);
                        background: rgba(0, 0, 0, 0.8);
                        color: white;
                        padding: 5px 10px;
                        border-radius: 4px;
                        font-size: 14px;
                        white-space: pre;
                        z-index: 10;
                        width: max-content;
                    }
                    .flatpickr-day.has-preference.selected {
                        border: 2px solid #333;
                        background-color: var(--preference-color) !important;
                    }
                `;
                document.head.appendChild(style);

                document.getElementById('preferenceForm').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const startDateTime = document.getElementById('startDateTime').value;
                    const endDateTime = document.getElementById('endDateTime').value;
                    
                    if (!startDateTime || !endDateTime) {
                        alert('Please select both start and end date/time');
                        return;
                    }

                    const start = new Date(startDateTime);
                    const end = new Date(endDateTime);

                    if (start >= end) {
                        alert('End date/time must be after start date/time');
                        return;
                    }

                    // Check for exact time overlap with only current user's preferences
                    const currentUserId = document.getElementById('userId').value;
                    const hasOverlap = existingPreferences.some(pref => {
                        // Only check preferences from the current user
                        if (pref.user_id != currentUserId) {
                            return false;
                        }

                        const prefStart = new Date(pref.start_date);
                        const prefEnd = new Date(pref.end_date);
                        
                        // Check if the time ranges overlap
                        const timeOverlap = (
                            (start >= prefStart && start < prefEnd) || // Start time falls within existing preference
                            (end > prefStart && end <= prefEnd) || // End time falls within existing preference
                            (start <= prefStart && end >= prefEnd) // New preference completely encompasses existing one
                        );
                        
                        return timeOverlap;
                    });

                    if (hasOverlap) {
                        alert('Selected time range overlaps with your existing preferences. Please choose a different time.');
                        return;
                    }

                    const response = await fetch('save_preference.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            eventId: document.getElementById('eventId').value,
                            startDate: startDateTime,
                            endDate: endDateTime,
                            preferenceScore: document.getElementById('preferenceScore').value
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error saving preference');
                    }
                });
            </script>
        <?php else: ?>
            <div class="error">
                <p>Event not found or invalid link.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
function getPreferenceColor($score) {
    // Convert score 1-10 to hue value (orange=30 to green=120)
    $hue = 30 + ($score - 1) * 10;
    return "hsl($hue, 70%, 70%)";
}
?> 