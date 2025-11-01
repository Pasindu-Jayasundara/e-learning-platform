<?php
return [
    // Email subjects
    'email.report.post.subject' => 'New report filed for post #{id}',
    'email.report.reply.subject' => 'New report filed for reply #{id}',

    // Email body templates (simple placeholders)
    'email.report.intro' => 'A new report was filed by {reporter_name} (user id: {reporter_id}).',
    'email.report.reason' => 'Reason: {reason}',
    'email.report.view_link' => 'View the item: {link}',

    // Digest
    'email.digest.subject' => 'Moderator digest: {count} open reports',
    'email.digest.intro' => 'There are {count} open reports pending review.',
    'email.digest.item' => '{when} — {type} #{id} in {course} — {reason}',
];
