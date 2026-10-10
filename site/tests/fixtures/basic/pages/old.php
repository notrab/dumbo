<?php

return new Dumbo\Site\Page(render: fn($context) => $context->redirect("/about", 301));
