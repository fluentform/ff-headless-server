Fluent Forms Headless Server
----
This is a proof of concept to connect a headless site with Fluent Forms in the backend server.
Please note that, We did not validate the data, but you will get a fair concept how it can work and update previous data or create new entry via http request. 

####Example Usage:

```
POST: https://domain.com/?ff_capture=yes&form_id=FORMID
BODY: form_data: JSON_FORM_DATA_AS_KEY_VALUE
```

You can also send the form_data as direct form submission 

[Get Fluent Forms](https://wordpress.org/plugins/fluentform)

#### Before you use
I recommend to verifying the request and use some kind of tokens to verify the request and process the data.