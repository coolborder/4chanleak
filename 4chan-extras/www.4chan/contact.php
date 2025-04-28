<?php
function title() {
 echo "Contact - 4chan";
}

function stylesheet() {
?>//s.4cdn.org/css/generic.css<?;
}

$top_box_count = 1;
function top_box_title_0() {
?>Contact<?
}

function top_box_content_0() {
?>
<p>Thank you for your interest in contacting 4chan!</p>
<p>Before proceeding, please ensure that your question hasn't already been answered on the <a href="/faq"><strong>F</strong>requently <strong>A</strong>sked <strong>Q</strong>uestions</a> or <a href="/feedback">Feedback</a> pages.
Additional pages you might find useful include: <a href="/news?all">News</a>, <a href="/rules">Rules</a>, <a href="/advertise">Advertise</a>, and <a href="/press">Press</a>.</p>
<p>Due to the high volume of e-mail we receive, we may not be able to respond to your inquiry immediately.</p>
<p><strong style="color: red;">Note: Unban requests are not accepted via e-mail. <span style="text-decoration: underline;">All unban requests will be ignored.</span> Appeal your ban using the <a href="https://www.4chan.org/banned">built-in form</a>!</strong></p>
<hr />
<p>via e-mail:</p>
<ul>
<li><a href="/advertise">Advertising</a> Inquiries: <a href="mailto:advertise@4chan.org">advertise@4chan.org</a> [Or use our <a href="https://www.4chan.org/advertise?selfserve">Self-Serve</a> option!]</li>
<li><a href="/press">Press</a> Requests: <a href="mailto:press@4chan.org">press@4chan.org</a></li>
<li>Business Development: <a href="mailto:bizdev@4chan.org">bizdev@4chan.org</a></li>
<li>DMCA Notice: See our <a href="/legal#dmca">DMCA Policy</a>.</li>
<li><a href="https://www.4chan.org/pass">4chan Pass</a> Help: <a href="mailto:4chanpass@4chan.org">4chanpass@4chan.org</a> [<a href="https://www.4chan.org/pass?reset">Forgot your PIN?</a>]</li>
</ul>
<p>via contact form:</p>
<ul>
<li>Ban Appeals: Use the <a href="https://www.4chan.org/banned">Ban Appeal</a> form. <strong style="color: red;">Unban requests are not accepted via e-mail.</strong></li>
<li>General Support &amp; Bug Reports: Use our <a href="/feedback">Feedback</a> page.</li>
<li>Vulnerability Disclosure: Visit our <a href="/security">Security</a> page.</li>
</ul>
<p>via Twitter:</p>
<ul>
<li><a href="//twitter.com/4chan" target="_blank">@4chan</a></li>
<?
}

$left_box_count = 0;

$right_box_count = 0;

include 'frontpage_template.php';
