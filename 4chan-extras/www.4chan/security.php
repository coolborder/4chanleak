<?php
function title() {
 echo "Security - 4chan";
}

function stylesheet() {
?>//s.4cdn.org/css/generic.css<?;
}

$top_box_count = 2;
function top_box_title_0() {
?>4chan Vulnerability Disclosure Program<?
}

function top_box_content_0() {
?>
<p>These Program Rules provide our guidelines for reporting vulnerabilities to 4chan.</p>

<p>If you believe you have identified a security vulnerability that could impact 4chan or its users, we ask you notify us right away. We will investigate all legitimate reports and do our best to quickly fix the problem. We request you follow our Vulnerability Disclosure Program Rules and HackerOne's <a href="https://hackerone.com/disclosure-guidelines" target="_blank" title="Disclosure Guidelines - HackerOne">Vulnerability Disclosure Guidelines</a> and make a good faith effort to avoid privacy violations, destruction of data, and interruption or degradation of our service during your research. And in keeping with 4chan's principles, feel free to submit your report using a pseudonym.</p>

<p>Note: This program is meant for vulnerabilities and security-related bugs. If you have a general bug report or site feedback, please submit it on our <a href="/feedback" title="4chan - Feedback">Feedback</a> page.</p>

<p><strong>Scope</strong></p>

<p>Websites and services operated by 4chan, which include:</p>

<p><ul><li>*.4chan.org</li>
<li>*.4cdn.org</li></ul></p>

<p>Please do not submit:<p>

<p><ul><li>Vulnerabilities reported by automated vulnerability scanning tools, unless you have a working proof-­of-­concept or reason to believe that this issue is exploitable. Many issues reported by these tools are low-hanging fruit and do not have a clear security implication for 4chan.</li>
<li>Vulnerabilities that rely on social engineering to be exploitable.</li>
<li>Clickjacking (<span class="code">X-Frame-Options</span>), HSTS (<span class="code">Strict-Transport-Security</span>), Internet Explorer specific headers (<span class="code">X-Content-Type</span> and <span class="code">X-XSS-Protection</span>), and <span class="code">HttpOnly</span> cookie reports. We already set these headers where we feel appropriate.</li></ul></p>

<p>Scope is limited strictly to software and hardware vulnerabilities—not people. As such, 4chan users, volunteers (janitors, moderators, etc), customers (4chan Pass users, advertisers, etc), and employees are entirely out of scope of this program.</p>

<p>Third-party software and services that we use, such as <a href="https://hackerone.com/nginx" target="_blank" title="nginx - HackerOne">nginx</a> and <a href="https://hackerone.com/cloudflare" target="_blank" title="CloudFlare - HackerOne">CloudFlare</a>, should be reported to the appropriate parties and are not eligible for a reward from us. We'd appreciate a head's up and will credit you on our <a href="#thanks">Thanks page</a> though!</p>

<p><strong>Eligibility &amp; Disclosure</strong></p>

<p>In order for your submission to be eligible:</p>

<p><ul><li>You must agree to all of our Vulnerability Disclosure Program Rules (this entire page).</li>
<li>You must follow HackerOne's <a href="https://hackerone.com/disclosure-guidelines" target="_blank" title="Disclosure Guidelines - HackerOne">Vulnerability Disclosure Guidelines</a>.</li>
<li>You must be the first person to responsibly disclose an unknown issue to us.</li>
<li>You must immediately report any vulnerability that allows access to personally identifiable information (PII), not copy or disseminate any PII obtained, and destroy any and all PII in your possession.</li>
<li>Please consolidate similar vulnerabilities across multiple files/domains into one report. Multiple reports of what is essentially the same vulnerability will be discarded and treated as one report.</li>
<li>All legitimate reports will be reviewed and assessed by 4chan's developer team to determine eligibility.</li>
<li>As mentioned in our <a href="/rules#global2" target="_blank" title="4chan - Rules">Rules</a>, 4chan's website and services are not intended for, or designed to attract, individuals under the age of 18. Reporters under the age of 18 will not be eligible to receive rewards.</li></ul></p>

<p><strong>Rewards</strong></p>

<p>For each eligible vulnerability report, the reporter will receive:</p>

<p><ul><li>Recognition on our <a href="#thanks">Thanks page</a>.</li>
<li>A <a href="https://www.4chan.org/pass" targer="_blank" title="4chan - Pass">4chan Pass</a> valid for one year ($20 USD value, subject to <a href="https://www.4chan.org/pass#termsofuse" target="_blank" title="4chan - Pass - Terms of Use">Terms of Use</a>).</li>
<li>We do not currently offer a cash reward.</li></ul></p>

<p><strong>Exclusions</strong></p>

<p>The following conditions are out of scope for our vulnerability disclosure program:</p>

<p><ul><li>Physical attacks against 4chan users, volunteers, customers, employees, offices, and data centers.</li>
<li>Social engineering of 4chan users, volunteers, customers, employees, or service providers.</li>
<li>Knowingly posting, transmitting, uploading, linking to, or sending any malware.</li>
<li>Pursuing vulnerabilities which send unsolicited bulk or unauthorized messages (spam), and/or denial of service (DoS) attacks.</li>
<li>Any vulnerability obtained through the compromise of a 4chan user, volunteer, customer, or employee account. If your vulnerability allows you to compromise one of these accounts, please report it to us immediately and do not press further without written permission.</li></ul></p>

<p><strong>Submissions &amp; Questions?</strong></p>

<p>Send us an e-mail at <a href="mailto:security@4chan.org">security@4chan.org</a>.</p>
<?
}

function top_box_title_1() {
?><span id="thanks">Thanks to...</span><?
}

function top_box_content_1() {
?>
<p>
   <ul> 
        <li>atom</li>
        <li>evanricafort0x003</li>
        <li>ng1</li>
        <li>rahulpratap</li>
        <li>reactors08</li>
        <li>tweetketan</li>
        <li>shubham</li>
        <li>ajaysinghnegi</li>
        <li>petyalevkin</li>
        <li>pranav</li>
        <li>adrianbelen</li>
        <li>fuzzbaba</li>
        <li>gopinath6</li>
        <li>prayas</li>
        <li>simon90</li>
        <li>xss</li>
        <li>hectorsschmector</li>
        <li>RyotaK</li>
    </ul>
</p>
<?
}

$left_box_count = 0;

$right_box_count = 0;

include 'frontpage_template.php';
