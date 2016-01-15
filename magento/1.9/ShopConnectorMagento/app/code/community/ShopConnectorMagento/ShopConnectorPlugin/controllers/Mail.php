<?php
class Mail2 {
	public $lastEmail, $lastReceivers;
	
	protected $body, $subject, $receivers = array();
	protected $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\n";
	protected $from, $to, $reply;
	
	public function __construct($body, $subject, $from, $receivers, $replyto = false) {
		$this->body = $body;
		$this->subject = $subject;
		$this->reply = "";
		$this->from = "From: ".key($from)." <".$from[key($from)].">\r\n";
		
		if(is_array($receivers) && count($receivers) > 0) {
			$to_header = "To: ";
			$to_tmp = $receivers_tmp = array();
			foreach($receivers as $key=>$r) {
				$to_tmp[] = $key." <".$r.">";
				$receivers_tmp[] = $r;
			}
			
			$to_header .= implode(", ", $to_tmp)."\r\n";
			$this->receivers = implode(", ", $receivers_tmp);
			$this->to = $to_header;
			if($replyto) $this->reply = 'Reply-To: kupony@goldencircle.pl' . "\r\n";
		} else return false;
	}

	public function send() {	
		$message = '
			<html>
				<head>
				  <title>'.$this->subject.'</title>
				</head>
				<body>
				  '.$this->body.'
				</body>
			</html>';
		return mail($this->receivers, "=?UTF-8?B?".base64_encode($this->subject)."?=", $message, $this->headers.$this->from.$this->to.$this->reply);
	}
}

?>