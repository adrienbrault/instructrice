# Instructrice

Obviously inspired by [instructor-php][instructor-php].

When working with LLMs using function calling, json mode, you need to expose schemas and parse outputs. 
With Symfony projects I've often had the same use case with user forms and api inputs. I've always had great success with [Symfony Forms][sf_form], from easy prototyping to complicated forms.

> Liform is a library for serializing Symfony Forms into JSON schema.

[Liform][liform] looks like a great way to convert a Symfony form into a JSON schema.

I want this library to support well both Class based models, to dynamically generated forms.

Symfony Form is also great for validation, because it's easy to add validation constraints.

It should support LLM APIs streaming modes, and stream partially submitted/hydrated form data.
Maybe it could even only target providers that support streaming, json/function modes.

This should focus on supporting OpenAI's chat completion API, although I'd like to support Claude, Groq.

Since this library relies on Symfony Forms, there's an opportunity to provide features for other symfony forms.
For example allowing a user to provide files, + a prompt.
So maybe when I am creating a product, I can upload the supplier documentation PDF.
Things to look into:
- [Unstructured][unstructured_docker]
- [Llama Parse][llama_parse]
- [EMLs][eml]

[DSPy][dspy] is very interesting. There are great ideas to be inspired by.

Ideally this library is good to prototype with, but can support more advanced extraction workflows
with few short learning, some sort of eval system, generating samples/output like DSPy, etc

[liform]: https://github.com/Limenius/Liform
[instructor-php]: https://github.com/cognesy/instructor-php/
[sf_form]: https://symfony.com/doc/current/components/form.html
[unstructured_docker]: https://unstructured-io.github.io/unstructured/installation/docker.html
[llama_parse]: https://github.com/run-llama/llama_parse
[eml]: https://en.wikipedia.org/wiki/Email#Filename_extensions
[dspy]: https://github.com/stanfordnlp/dspy
