@extends('visit-transfer.site.application._layout')

@section('vt-content')
    <div class="row" id="submissionHelp">
        <div class="col-md-12">
            {!! HTML::panelOpen("Submission", ["type" => "fa", "key" => "tick"]) !!}
            <div class="row">
                <div class="col-md-10 col-md-offset-1">

                    <p>

                    </p>

                </div>

                {!! Form::open(["route" => ["visiting.application.submit.post", $application->public_id], "method" => "POST"]) !!}
                    <div class="col-md-10 col-md-offset-1 text-center">
                        <div class="form-group row">
                            {!! Form::label("submission_terms", "I confirm that the details within this application are correct to the best of my knowledge and that my application will be rejected if any details are inaccurate. I understand that after I submit this application I will not be able to amend any details (e.g. referee details) and in the event of any details being incorrect, I will need to start a new application.&nbsp;&nbsp;"),
                            Form::checkbox("submission_terms", true, false) !!}
                        </div>
                    </div>

                    <div class="col-md-10 col-md-offset-1 text-center">
                        <button type="submit" class="btn btn-success">SUBMIT APPLICATION</button>
                    </div>
                {!! Form::close() !!}

            </div>
            {!! HTML::panelClose() !!}
        </div>
    </div>
@stop
