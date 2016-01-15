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
		
                $conv = array(
                    '\u0105' => 'ą',
                    '\u0107' => 'ć',
                    '\u0119' => 'ę',
                    '\u0142' => 'ł',
                    '\u0144' => 'ń',
                    '\u00f3' => 'ó',
                    '\u015b' => 'ś',
                    '\u017c' => 'ż',
                    '\r' => '',
                    '\n' => '',
                    '\t' => '',
                    '\"' => '"',
                    '\/' => '/',
                );
                
                $this->body = str_replace(array_keys($conv), array_values($conv), $this->body);
                
		if(is_array($receivers) && count($receivers) > 0) {
			$to_header = "To: ";
                        
			$to_tmp = $receivers_tmp = array();
			foreach($receivers as $key => $r) {
				$to_tmp[] = $key." <".$r.">";
				$receivers_tmp[] = $r;
			}
			
			$to_header .= implode(", ", $to_tmp)."\r\n";
			$this->receivers = implode(", ", $receivers_tmp);
			$this->to = $to_header;
			if($replyto) $this->reply = 'Reply-To: kupony@shopconnector.pl' . "\r\n";
		} else return false;
	}

	public function send() {	
            $headers = $this->headers;
            $headers .= $this->from;
            //$headers .= $this->to;
            $headers .= $this->reply;
            return mail($this->receivers, "=?UTF-8?B?".base64_encode($this->subject)."?=", $this->body, $headers);
	}
}

?>