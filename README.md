WXML
==================================

WXML is created to simplify everyday work with XML files.
When created, it was tried to be analoguous of SimpleXMLElement
 but with support of namespaces.

Examples
-------------------

You can trace path as you would do it with SimpleXMLElement:

	$a = new WXML('<root><qa><question>the answer to life the universe and everything</question><answer>42</answer></qa></root>');
	echo (string)$a->root->qa->answer;

Outputs:

	42

Namespace example:

	$xml = <<DOC
    <xs:root xmlns:xs="http://example.org">
      <xs:qa>
        <que:question xmlns:q="http://question.org">
        How is it going?
        </que:question>
        <ans:answer xmlns:ans="http://answer.org">
        Fine.
        </ans:answer>
      </xs:qa>
      <qa xmlns="http://unusual.org">
        <que:question xmlns:q="http://question.org">
        How are you doing?
        </que:question>
        <ans:answer xmlns:ans="http://answer.org">
        Fine.
        </ans:answer>
      </qa>
    </xs:root>
  DOC;
  $xml = new WXML($xml);
  // get all answers (yes, without dealing with namespaces)
  $p[] = $xml->root->qa->answer;
  // let's took only the second question
  $xml->registerNamespace('http://unusual.org', 'q');
  $p[] = $xml->root->{'q:qa'}->answer;
  foreach ($p as $t) {
    echo $t->asXML()."\n";
  }


Assumptions
----------------------

Current assumptions:

1.  We only work with utf-8 version of xml.
2.	I don't think that we should initialize object with the root element of xml as it does in SimpleXMLElement.
3.  There is no schema validations yet.
