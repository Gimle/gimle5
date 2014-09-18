Coding standards
----------------

The coding standards will follow the PSR standard with some exceptions and additions.

**Indentation whitespace**
For some a religion, and others do not mind eighter way. First of all, lets make sure we talk about the same thing. Whitespace in indentation, and not in alignment. Some people like two spaces, some like four, some like a tab, and some have other wishes. One thing is for sure, a tab can always be converted later on into a certain amount of spaces, but when people mix indention and alignment in the code, and when people use different amount of spaces, it can be hard to standardize later on. Using tabs, different people can set up their editor to look like they like the best. And if they really want to, they can easially convert it their preferred amount of spaces. This is why, this project use a tab instead of spaces.

**Function declaration vs function calling**
For function declarations there should be a space before the first parentheses. In a function call there should not.

	function helloWorld ()
	{
		return 'Foo';
	}

	helloWrold();

**Class names**
Class names can not contain underscores. We use namespaced enviroment, so no need for fallback.

**Trailing whitesapce**
Often lead into bigger diffs than the code that was changes, and is not needed so it should always be trimmed.

**Default quotes are single quotes.**
Single quotes restrict the use of variables in them, and makes cleaner code. You may also use double quotes for html output without escaping it which is more common to do than to output single quotes. When using double quotes, variables should be in curly brackets. This ease up readability, and can be used with arrays.

**Trailing slash**
For directory names, the trailing slash should always be present.

**Curly brackets**
Always use curly brackets around if statements.

**File ends with a newline.**

**Unix newlines.**

**Naming**

- Files are lower cased.
- Functions and methods are camelCased.
- Objects are PascalCased.
- Variables are $variableName.
- Constants are UPPER_CASED
- Acronyms: XmlHttpRequest (Objects), xmlHttpRequest (Functions, methods and variables).
- SQL are all lowercase + underscores.

**Class content sorting**

Sorted alphabetically in sections in the following order:

1. Constants.
2. Public vars.
3. Protected vars.
4. Private vars.
5. Magic methods.
6. Public methods.
7. Protected methods.
8. Private methods.
