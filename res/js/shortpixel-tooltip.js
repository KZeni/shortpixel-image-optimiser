var ShortPixelToolTip = function(reserved, processor)
{

    this.Init = function()
    {

        var paused =  localStorage.getItem('tooltipPause'); // string returns, not boolean
        if (paused == 'true')
        {
          console.log('manual paused (tooltip)');
          processor.PauseProcess();
          //processor.isManualPaused = true;
        }
        var control = document.querySelector('.ab-item .controls');
        control.addEventListener('click', this.ToggleProcessing.bind(this));

        this.ToggleIcon();

        if (processor.isManualPaused == true)
        {
          //  console.log('manual paused (tooltip)');
          //  processor.CheckActive();
            this.ProcessPause();
        }


      window.addEventListener('shortpixel.processor.paused', this.ProcessChange.bind(this));
    }
    this.GetToolTip = function() // internal function please.
    {
        return document.querySelector('li.shortpixel-toolbar-processing');
    }
		this.InitStats = function()
		{
		      var processData = ShortPixelProcessorData.startData;
					this.RefreshStats(processData.media.stats, 'media');
					this.RefreshStats(processData.custom.stats, 'custom');
					this.RefreshStats(processData.total.stats, 'total');
		}
		// Used to put a 'todo' number in the tooltip when processing
    this.RefreshStats = function(stats, type)
    {
				var neededType;

				if (processor.screen.isMedia == true && processor.screen.isCustom == true)
					neededType = 'total';
				else if (processor.screen.isMedia == true && processor.screen.isCustom == false)
					neededType = 'media';
				else if (processor.screen.isMedia == false && processor.screen.isCustom == true)
					neededType = 'custom';

				if (neededType !== type)
					return;

        var toolTip = this.GetToolTip();
        var statTip = toolTip.querySelector('.stats');
				console.log('refresh', stats);

        if (statTip == null)
          return;

				var number = stats.in_queue + stats.in_process;
        statTip.textContent = number;

        if (statTip.classList.contains('hidden') && number > 0)
          statTip.classList.remove('hidden');
        else if (! statTip.classList.contains('hidden') && number == 0)
          statTip.classList.add('hidden');
    }
    this.ToggleProcessing = function(event)
    {
       event.preventDefault();
       //event.stopProp

       if (processor.isManualPaused == false)
       {
        //  processor.isManualPaused = true;
          processor.PauseProcess();
          localStorage.setItem('tooltipPause','true');
          this.ProcessPause();
       }
        else
       {
        //  processor.isManualPaused = false;
          processor.ResumeProcess();
          localStorage.setItem('tooltipPause','false');
          console.log('ToogleProc' + localStorage.getItem('tooltipPause'));
          this.ProcessResume();
       }

       processor.CheckActive();

       /*if (processor.isActive)
          processor.RunProcess(); */

    }

    this.ToggleIcon = function()
    {
      var controls = document.querySelectorAll('.ab-item .controls > span');

      for(i = 0; i < controls.length; i++)
      {
          var control = controls[i];
          if (control.classList.contains('pause'))
          {
            if (processor.isManualPaused == true)
              control.classList.add('hidden');
            else
              control.classList.remove('hidden');
          }
           else if (control.classList.contains('play'))
          {
            if (processor.isManualPaused == false)
              control.classList.add('hidden');
            else
              control.classList.remove('hidden');
          }
      }
    }

    this.DoingProcess = function()
    {
        tooltip = this.GetToolTip();
        tooltip.classList.remove('shortpixel-hide');
        tooltip.classList.add('shortpixel-processing');
    }

    this.AddNotice = function(message)
    {
      var tooltip = this.GetToolTip(); // li.shortpixel-toolbar-processing
      var toolcontent = tooltip.querySelector('.ab-item');

      var alert = document.createElement('div');
      alert.className = 'toolbar-notice toolbar-notice-error';
      alert.innerHTML = message;

      alertChild = toolcontent.parentNode.insertBefore(alert, toolcontent.nextSibling);

      window.setTimeout (this.RemoveNotice.bind(this), 5000, alertChild);
    }


    this.RemoveNotice = function(notice)
    {
        notice.style.opacity = 0;
        window.setTimeout(function () { notice.remove() }, 2000);

    }
    this.ProcessResume = function()
    {
      tooltip = this.GetToolTip();

      tooltip.classList.remove('shortpixel-paused');
      tooltip.classList.add('shortpixel-processing');
      this.ToggleIcon();

    }
    this.ProcessEnd = function()
    {
        tooltip = this.GetToolTip();

        tooltip.classList.add('shortpixel-hide');
        tooltip.classList.remove('shortpixel-processing');
    }
    this.ProcessPause = function()
    {
        tooltip = this.GetToolTip();

        tooltip.classList.add('shortpixel-paused');
        tooltip.classList.remove('shortpixel-processing');
        tooltip.classList.remove('shortpixel-hide');
        this.ToggleIcon();

    }
    this.ProcessChange = function(e)
    {
        var detail = e.detail;

        if (detail.paused == false)
           this.ProcessResume();
        else
           this.ProcessPause();
    }
    this.HandleError = function()
    {

    }

    this.Init();
} // tooltip.
