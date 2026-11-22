<?php
require_once __DIR__ . '/../../lexicon/includes/Parsedown.php';
$parsedown = new Parsedown();
$parsedown->setSafeMode(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<!--syntax highlighter -->
	<link href="../../lexicon/includes/prism.css" rel="stylesheet" />
	<title>hi</title>
</head>
<body>
<pre><code class="language-css">p { color: red }</code></pre>
and this 
<pre class="line-numbers" data-line="2"><code class="language-markup"><?php echo $parsedown->line('<input placeholder="Date" type="text" onfocus="(this.type=\'datetime-local\')" onblur="(this.type=\'text\')" id="date">
more stuff
even more'); ?></code></pre>
	<script src="../../lexicon/includes/prism.js"></script>
</body>
</html>