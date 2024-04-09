# Instructrice

Obviously inspired by [instructor-php][instructor-php].

When working with LLMs using function calling, json mode, you need to expose schemas and parse outputs. 
With Symfony projects I've often had the same use case with user forms and api inputs. I've always had great success with [Symfony Forms][sf_form], from easy prototyping to complicated forms.

> Liform is a library for serializing Symfony Forms into JSON schema.

[Liform][liform] looks like a great way to convert a Symfony form into a JSON schema.

I want this library to support well both Class based models, to dynamically generated forms.

Symfony Form is also great for validation, because it's easy to add validation constraints.

It should support LLM APIs streaming modes, and stream partially submitted/hydrated form data.

This should focus on supporting OpenAI's chat completion API, although I'd like to support Claude, Groq.

[liform]: https://github.com/Limenius/Liform
[instructor-php]: https://github.com/cognesy/instructor-php/
[sf_form]: https://symfony.com/doc/current/components/form.html
