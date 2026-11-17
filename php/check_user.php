<?php
//======================================================================
// ROLE CHECK: only members may open the page that includes this file.
// Anyone else is sent back to their own dashboard with a message.
// Include after php/session.php.
//======================================================================
require_role(ROLE_MEMBER);
