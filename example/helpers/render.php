<?php

return function($template, array $viewObject = [], $echo = false)
{
	$templatePath = __DIR__ . '/../templates/' . $template;

	if (is_readable($templatePath))
	{
		ob_start();

		extract($viewObject);
		include $templatePath;

		$contents = ob_get_contents();
		ob_end_clean();

		if ($echo)
		{
			echo $contents;
		}

		return $contents;
	}
	else
	{
		throw new Exception('Template does not exists [' . $template . ']');
	}
};
