h1. WXML

==================================

WXML is created to simplify everyday work with XML files.
When created, it was tried to be analoguous of SimpleXMLElement
 but with support of namespaces.

============================
h2. Examples

You can trace path as you would do it with SimpleXMLElement:
'''
$a = new WXML('<root><qa><question>the answer to life the universe and everything</question><answer>42</answer></qa></root>');
echo (string)$a->qa->answer
'''
Outputs:
'''
42
'''

======================================

h2. Assumptions

Current assumptions:
# We only work with utf-8 version of xml.
# There is no schema validations yet.
